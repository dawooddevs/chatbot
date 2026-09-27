<?php
declare(strict_types=1);

namespace App;

/**
 * Imports the CSV from WooCommerce -> Products -> Export.
 *
 * Variation rows (sizes, colours) are folded into their parent so the parent
 * carries a price range and stock. The export has no product URL, so rows
 * link to a store search for the product name when the site address is known.
 */
final class ProductCsv
{
    public const MAX_BYTES = 30 * 1024 * 1024;

    /** @return array{imported:int, skipped:int} */
    public static function import(array $site, string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('The CSV could not be opened.');
        }

        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (!is_array($header)) {
            fclose($handle);
            throw new \RuntimeException('The CSV is empty.');
        }
        // Excel and some exporters prefix a byte-order mark.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
        $columns = array_flip(array_map(static fn ($h): string => strtolower(trim((string)$h)), $header));

        foreach (['id', 'name'] as $required) {
            if (!isset($columns[$required])) {
                fclose($handle);
                throw new \RuntimeException('This does not look like a WooCommerce product export (no "' . ucfirst($required) . '" column).');
            }
        }

        $col = static function (array $row, string $name) use ($columns): string {
            return isset($columns[$name]) ? trim((string)($row[$columns[$name]] ?? '')) : '';
        };

        $parents = [];
        $variations = [];
        $skipped = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }
            $type = strtolower($col($row, 'type'));
            $published = $col($row, 'published');

            if (str_contains($type, 'variation')) {
                $parentId = (int)preg_replace('/\D/', '', $col($row, 'parent'));
                if ($parentId > 0 && $published !== '-1' && $published !== '0') {
                    $variations[$parentId][] = [
                        'price' => self::number($col($row, 'sale price')) ?? self::number($col($row, 'regular price')),
                        'in_stock' => self::inStock($col($row, 'in stock?'), $col($row, 'stock')),
                    ];
                }
                continue;
            }

            $visibility = strtolower($col($row, 'visibility in catalog'));
            if ($published === '-1' || $published === '0' || $visibility === 'hidden') {
                $skipped++;
                continue;
            }

            $id = (int)$col($row, 'id');
            if ($id <= 0) {
                $skipped++;
                continue;
            }
            $parents[$id] = $row;
        }
        fclose($handle);

        $settings = StoreSync::settings($site);
        $storeUrl = $settings['url'];
        $token = 'csv-' . bin2hex(random_bytes(4));
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO products (site_id, external_id, name, sku, product_type, price, max_price, regular_price, on_sale,
                currency_prefix, currency_suffix, in_stock, stock_text, brands, categories, tags, attributes,
                summary, permalink, image, search_text, sync_token, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), sku = VALUES(sku), product_type = VALUES(product_type),
                price = VALUES(price), max_price = VALUES(max_price), regular_price = VALUES(regular_price),
                on_sale = VALUES(on_sale), in_stock = VALUES(in_stock), stock_text = VALUES(stock_text),
                brands = VALUES(brands), categories = VALUES(categories), tags = VALUES(tags),
                attributes = VALUES(attributes), summary = VALUES(summary),
                permalink = COALESCE(products.permalink, VALUES(permalink)),
                image = VALUES(image), search_text = VALUES(search_text),
                sync_token = VALUES(sync_token), updated_at = NOW()'
        );

        $imported = 0;
        $pdo->beginTransaction();
        try {
            foreach ($parents as $id => $row) {
                $name = self::plain($col($row, 'name'));
                if ($name === '') {
                    $skipped++;
                    continue;
                }
                $regular = self::number($col($row, 'regular price'));
                $sale = self::number($col($row, 'sale price'));
                $price = $sale ?? $regular;
                $maxPrice = null;
                $inStock = self::inStock($col($row, 'in stock?'), $col($row, 'stock'));

                if (isset($variations[$id])) {
                    $prices = array_filter(array_column($variations[$id], 'price'), static fn ($p): bool => $p !== null);
                    if ($prices !== []) {
                        $price = min($prices);
                        $maxPrice = max($prices) > $price ? max($prices) : null;
                    }
                    $inStock = in_array(true, array_column($variations[$id], 'in_stock'), true);
                }

                $stock = $col($row, 'stock');
                $attributes = [];
                for ($n = 1; $n <= 10; $n++) {
                    $attrName = $col($row, "attribute {$n} name");
                    $attrValue = $col($row, "attribute {$n} value(s)");
                    if ($attrName !== '' && $attrValue !== '') {
                        $attributes[] = $attrName . ': ' . $attrValue;
                    }
                }
                $attributes = implode('; ', $attributes);
                $categories = str_replace(' > ', ' / ', $col($row, 'categories'));
                $tags = $col($row, 'tags');
                $brands = $col($row, 'brands');
                $summary = self::plain($col($row, 'short description')) ?: self::plain($col($row, 'description'));
                $sku = $col($row, 'sku');
                $image = trim(explode(',', $col($row, 'images'))[0] ?? '');
                $link = $storeUrl !== '' ? $storeUrl . '/?s=' . rawurlencode($name) . '&post_type=product' : null;
                // "Type" can read "simple, downloadable, virtual"; the first word is the kind.
                $productType = trim(explode(',', strtolower($col($row, 'type')))[0]) ?: 'simple';

                $stmt->execute([
                    (int)$site['id'], $id, mb_substr($name, 0, 255), $sku !== '' ? mb_substr($sku, 0, 100) : null,
                    mb_substr($productType, 0, 20),
                    $price, $maxPrice, $regular, $sale !== null && $regular !== null && $sale < $regular ? 1 : 0,
                    '$', '', $inStock ? 1 : 0,
                    $stock !== '' ? ($inStock ? $stock . ' in stock' : 'Out of stock') : ($inStock ? 'In stock' : 'Out of stock'),
                    $brands !== '' ? mb_substr($brands, 0, 255) : null,
                    $categories ?: null, $tags ?: null, $attributes ?: null,
                    $summary !== '' ? mb_substr($summary, 0, 500) : null,
                    $link, $image !== '' ? mb_substr($image, 0, 500) : null,
                    trim(implode(' ', array_filter([
                        $name, $sku, $brands, $categories, $tags, $attributes,
                        mb_substr(self::plain($col($row, 'description')), 0, 2000),
                    ]))),
                    $token,
                ]);
                $imported++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $settings['stats'] = StoreSync::stats((int)$site['id']);
        StoreSync::saveSettings((int)$site['id'], $settings);

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    private static function number(string $value): ?float
    {
        $value = str_replace([',', '$', ' '], ['', '', ''], $value);
        return $value !== '' && is_numeric($value) ? (float)$value : null;
    }

    private static function inStock(string $flag, string $stock): bool
    {
        if ($flag !== '') {
            return $flag === '1' || strtolower($flag) === 'yes' || strtolower($flag) === 'backorder';
        }
        return $stock === '' || (float)$stock > 0;
    }

    private static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['\\n', '<br>', '<br />'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
