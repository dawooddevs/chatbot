<?php
declare(strict_types=1);

namespace App;

/**
 * Pulls a WordPress / WooCommerce site into the bot:
 *  - pages and posts become knowledge-base documents (WordPress REST API),
 *  - products land in the products table (WooCommerce Store API).
 *
 * Work is split into one API page per step so a sync never runs into shared
 * hosting's PHP time limit; the admin panel drives the steps and shows
 * progress, and bin/sync.php runs them back to back from cron.
 */
final class StoreSync
{
    public const PHASES = ['pages', 'posts', 'products'];

    private const PER_PAGE = ['pages' => 10, 'posts' => 10, 'products' => 100];
    private const DOC_TYPES = ['pages' => 'wp_page', 'posts' => 'wp_post'];

    public const DEFAULTS = [
        'url' => '',
        'pages' => 1,
        'posts' => 1,
        'products' => 1,
        'last_sync_at' => null,
        'last_error' => null,
        'stats' => ['products' => 0, 'pages' => 0, 'posts' => 0],
    ];

    public static function settings(array $site): array
    {
        $stored = json_decode((string)($site['store'] ?? ''), true);
        return array_replace(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    public static function saveSettings(int $siteId, array $settings): void
    {
        Database::run('UPDATE sites SET store = ?, updated_at = NOW() WHERE id = ?', [
            json_encode(array_replace(self::DEFAULTS, $settings), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $siteId,
        ]);
    }

    /** Reduces whatever was typed to the site root, e.g. https://shop.com */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        Scraper::assertPublicUrl($url);
        $parts = parse_url($url);
        $root = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
        if (!empty($parts['port'])) {
            $root .= ':' . $parts['port'];
        }
        // A store installed in a sub-folder keeps that folder.
        $path = rtrim((string)($parts['path'] ?? ''), '/');
        return $root . $path;
    }

    /** @return string[] the phases switched on for this site */
    public static function phases(array $settings): array
    {
        return array_values(array_filter(self::PHASES, static fn (string $p): bool => !empty($settings[$p])));
    }

    public static function newToken(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Processes one API page of one phase.
     *
     * @return array{phase:string, page:int, total_pages:int, total:int, processed:int, next_page:?int}
     */
    public static function step(array $site, string $token, string $phase, int $page): array
    {
        if (!in_array($phase, self::PHASES, true)) {
            throw new \InvalidArgumentException('Unknown sync phase.');
        }
        $settings = self::settings($site);
        if ($settings['url'] === '') {
            throw new \RuntimeException('Add the website address first.');
        }

        $page = max(1, $page);
        $perPage = self::PER_PAGE[$phase];
        $endpoint = $phase === 'products'
            ? '/wp-json/wc/store/v1/products?per_page=' . $perPage . '&page=' . $page
            : '/wp-json/wp/v2/' . $phase . '?per_page=' . $perPage . '&page=' . $page
                . '&status=publish&_fields=id,title,link,content';

        [$items, $totalPages, $total] = self::fetchJson($settings['url'] . $endpoint);

        $processed = $phase === 'products'
            ? self::upsertProducts((int)$site['id'], $items, $token)
            : self::upsertDocuments($site, $items, self::DOC_TYPES[$phase], $token);

        return [
            'phase' => $phase,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'processed' => $processed,
            'next_page' => $page < $totalPages ? $page + 1 : null,
        ];
    }

    /**
     * Removes what the finished phases no longer contain and records totals.
     *
     * @param string[] $completed phases that ran to their last page
     */
    public static function finish(array $site, string $token, array $completed): array
    {
        $siteId = (int)$site['id'];

        foreach ($completed as $phase) {
            if ($phase === 'products') {
                Database::run(
                    'DELETE FROM products WHERE site_id = ? AND (sync_token IS NULL OR sync_token <> ?)',
                    [$siteId, $token]
                );
                continue;
            }
            if (!isset(self::DOC_TYPES[$phase])) {
                continue;
            }
            $stale = Database::all(
                'SELECT id FROM documents WHERE site_id = ? AND source_type = ? AND (sync_token IS NULL OR sync_token <> ?)',
                [$siteId, self::DOC_TYPES[$phase], $token]
            );
            foreach ($stale as $row) {
                KnowledgeBase::deleteDocument($siteId, (int)$row['id']);
            }
        }

        $settings = self::settings($site);
        $settings['last_sync_at'] = date('c');
        $settings['last_error'] = null;
        $settings['stats'] = self::stats($siteId);
        self::saveSettings($siteId, $settings);

        return $settings['stats'];
    }

    public static function fail(array $site, string $message): void
    {
        $settings = self::settings($site);
        $settings['last_error'] = mb_substr($message, 0, 300);
        self::saveSettings((int)$site['id'], $settings);
    }

    public static function stats(int $siteId): array
    {
        return [
            'products' => (int)Database::value('SELECT COUNT(*) FROM products WHERE site_id = ?', [$siteId]),
            'pages' => (int)Database::value("SELECT COUNT(*) FROM documents WHERE site_id = ? AND source_type = 'wp_page'", [$siteId]),
            'posts' => (int)Database::value("SELECT COUNT(*) FROM documents WHERE site_id = ? AND source_type = 'wp_post'", [$siteId]),
        ];
    }

    /** Runs every enabled phase to the end in one process (cron / CLI). */
    public static function runAll(array $site, ?callable $log = null): array
    {
        $log = $log ?? static function (string $line): void {
        };
        $settings = self::settings($site);
        $token = self::newToken();
        $completed = [];

        foreach (self::phases($settings) as $phase) {
            $page = 1;
            do {
                $result = self::step($site, $token, $phase, $page);
                $log(sprintf('%s: page %d/%d, %d items', $phase, $result['page'], max(1, $result['total_pages']), $result['processed']));
                $page = $result['next_page'];
            } while ($page !== null);
            $completed[] = $phase;
        }

        return self::finish($site, $token, $completed);
    }

    /**
     * @return array{0: array<int, array>, 1: int, 2: int} items, total pages, total items
     */
    private static function fetchJson(string $url): array
    {
        $response = Http::request('GET', $url, ['Accept' => 'application/json'], null, 45);

        // WordPress answers 400 for a page past the end; treat that as empty.
        if ($response['status'] === 400 && str_contains($response['body'], 'invalid_page_number')) {
            return [[], 0, 0];
        }
        if ($response['status'] === 404) {
            throw new \RuntimeException('That site does not expose ' . (str_contains($url, '/wc/') ? 'the WooCommerce Store API' : 'the WordPress REST API') . ' (HTTP 404).');
        }
        if ($response['status'] >= 400) {
            throw new \RuntimeException('The site answered HTTP ' . $response['status'] . '. A security plugin or firewall may be blocking API access.');
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new \RuntimeException('The site did not return JSON. A security plugin or firewall may be blocking API access.');
        }

        $total = (int)($response['headers']['x-wp-total'] ?? count($data));
        $totalPages = (int)($response['headers']['x-wp-totalpages'] ?? ($data === [] ? 0 : 1));

        return [array_values($data), $totalPages, $total];
    }

    private static function upsertProducts(int $siteId, array $items, string $token): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO products (site_id, external_id, name, sku, product_type, price, max_price, regular_price, on_sale,
                currency_prefix, currency_suffix, in_stock, stock_text, brands, categories, tags, attributes,
                summary, permalink, image, search_text, sync_token, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), sku = VALUES(sku), product_type = VALUES(product_type),
                price = VALUES(price), max_price = VALUES(max_price), regular_price = VALUES(regular_price),
                on_sale = VALUES(on_sale), currency_prefix = VALUES(currency_prefix),
                currency_suffix = VALUES(currency_suffix), in_stock = VALUES(in_stock),
                stock_text = VALUES(stock_text), brands = VALUES(brands), categories = VALUES(categories),
                tags = VALUES(tags), attributes = VALUES(attributes), summary = VALUES(summary),
                permalink = VALUES(permalink), image = VALUES(image), search_text = VALUES(search_text),
                sync_token = VALUES(sync_token), updated_at = NOW()'
        );

        $count = 0;
        $pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                if (!is_array($item) || empty($item['id'])) {
                    continue;
                }
                $row = self::mapStoreProduct($item);
                $stmt->execute([
                    $siteId, (int)$item['id'], $row['name'], $row['sku'], $row['type'],
                    $row['price'], $row['max_price'], $row['regular_price'], $row['on_sale'],
                    $row['prefix'], $row['suffix'], $row['in_stock'], $row['stock_text'],
                    $row['brands'], $row['categories'], $row['tags'], $row['attributes'],
                    $row['summary'], $row['permalink'], $row['image'], $row['search_text'], $token,
                ]);
                $count++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $count;
    }

    /** Maps one WooCommerce Store API product onto our columns. */
    public static function mapStoreProduct(array $p): array
    {
        $prices = is_array($p['prices'] ?? null) ? $p['prices'] : [];
        $divisor = 10 ** max(0, (int)($prices['currency_minor_unit'] ?? 2));
        $money = static function ($value) use ($divisor): ?float {
            return ($value === null || $value === '') ? null : round(((int)$value) / $divisor, 2);
        };

        $range = is_array($prices['price_range'] ?? null) ? $prices['price_range'] : null;
        $price = $range ? $money($range['min_amount'] ?? null) : $money($prices['price'] ?? null);
        $maxPrice = $range ? $money($range['max_amount'] ?? null) : null;

        $names = static function ($list): string {
            if (!is_array($list)) {
                return '';
            }
            return implode(', ', array_filter(array_map(
                static fn ($entry): string => is_array($entry) ? self::plain((string)($entry['name'] ?? '')) : '',
                $list
            )));
        };

        $attributes = [];
        foreach ((array)($p['attributes'] ?? []) as $attribute) {
            if (!is_array($attribute)) {
                continue;
            }
            $values = $names($attribute['terms'] ?? []);
            if ($values !== '') {
                $attributes[] = self::plain((string)($attribute['name'] ?? '')) . ': ' . $values;
            }
        }
        $attributes = implode('; ', $attributes);

        $description = self::plain((string)($p['short_description'] ?? '')) ?: self::plain((string)($p['description'] ?? ''));
        $longText = self::plain((string)($p['description'] ?? ''));
        $name = self::plain((string)($p['name'] ?? '')) ?: 'Product ' . ($p['id'] ?? '');
        $brands = $names($p['brands'] ?? []);
        $categories = $names($p['categories'] ?? []);
        $tags = $names($p['tags'] ?? []);
        $sku = trim((string)($p['sku'] ?? ''));

        return [
            'name' => mb_substr($name, 0, 255),
            'sku' => $sku !== '' ? mb_substr($sku, 0, 100) : null,
            'type' => mb_substr((string)($p['type'] ?? 'simple'), 0, 20),
            'price' => $price,
            'max_price' => $maxPrice !== null && $maxPrice !== $price ? $maxPrice : null,
            'regular_price' => $money($prices['regular_price'] ?? null),
            'on_sale' => !empty($p['on_sale']) ? 1 : 0,
            'prefix' => html_entity_decode((string)($prices['currency_prefix'] ?? '$'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'suffix' => html_entity_decode((string)($prices['currency_suffix'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'in_stock' => !array_key_exists('is_in_stock', $p) || !empty($p['is_in_stock']) ? 1 : 0,
            'stock_text' => mb_substr(self::plain((string)($p['stock_availability']['text'] ?? '')), 0, 100) ?: null,
            'brands' => mb_substr($brands, 0, 255) ?: null,
            'categories' => $categories ?: null,
            'tags' => $tags ?: null,
            'attributes' => $attributes ?: null,
            'summary' => $description !== '' ? mb_substr($description, 0, 500) : null,
            'permalink' => mb_substr((string)($p['permalink'] ?? ''), 0, 500) ?: null,
            'image' => mb_substr((string)($p['images'][0]['thumbnail'] ?? $p['images'][0]['src'] ?? ''), 0, 500) ?: null,
            'search_text' => trim(implode(' ', array_filter([
                $name, $sku, $brands, $categories, $tags, $attributes, mb_substr($longText, 0, 2000),
            ]))),
        ];
    }

    private static function upsertDocuments(array $site, array $items, string $type, string $token): int
    {
        $siteId = (int)$site['id'];
        $count = 0;

        foreach ($items as $item) {
            if (!is_array($item) || empty($item['link'])) {
                continue;
            }
            $title = self::plain((string)($item['title']['rendered'] ?? '')) ?: (string)$item['link'];
            $text = Scraper::htmlToText((string)($item['content']['rendered'] ?? ''));
            if (trim($text) === '') {
                continue;
            }
            $link = mb_substr((string)$item['link'], 0, 500);

            $existing = Database::first(
                'SELECT id, title, content FROM documents WHERE site_id = ? AND source_type = ? AND source_url = ? LIMIT 1',
                [$siteId, $type, $link]
            );

            // Unchanged pages keep their embeddings; only the sync marker moves.
            if ($existing && $existing['title'] === $title && $existing['content'] === Chunker::normalize($text)) {
                Database::run('UPDATE documents SET sync_token = ? WHERE id = ?', [$token, $existing['id']]);
                $count++;
                continue;
            }

            $documentId = KnowledgeBase::saveDocument($siteId, [
                'title' => $title,
                'content' => $text,
                'source_type' => $type,
                'source_url' => $link,
            ], $existing ? (int)$existing['id'] : null);
            Database::run('UPDATE documents SET sync_token = ? WHERE id = ?', [$token, $documentId]);

            try {
                KnowledgeBase::indexDocument($documentId, $site);
            } catch (\Throwable $e) {
                // Stored for keyword search; the document list shows the reason.
                error_log('[chatbot] sync indexing: ' . $e->getMessage());
            }
            $count++;
        }

        return $count;
    }

    private static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
