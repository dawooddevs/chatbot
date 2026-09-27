<?php
declare(strict_types=1);

namespace App;

/**
 * Catalogue search behind the chat's search_products tool and the admin list.
 * Uses the MySQL full-text index when it exists and ranked LIKE matching
 * otherwise, which is fast enough for tens of thousands of rows.
 */
final class ProductSearch
{
    public const TOOL_LIMIT = 6;

    public static function count(int $siteId): int
    {
        return (int)Database::value('SELECT COUNT(*) FROM products WHERE site_id = ?', [$siteId]);
    }

    /**
     * @param array{max_price?:float|null, min_price?:float|null, in_stock_only?:bool, category?:string|null, brand?:string|null} $filters
     * @return array<int, array>
     */
    public static function search(int $siteId, string $query, array $filters = [], int $limit = self::TOOL_LIMIT): array
    {
        $query = trim(mb_substr($query, 0, 200));
        [$where, $params] = self::filters($siteId, $filters);

        // An exact SKU or UPC beats any text match.
        if ($query !== '' && preg_match('/^[A-Za-z0-9._\-]{4,}$/', $query)) {
            $exact = Database::all(
                'SELECT * FROM products WHERE ' . $where . ' AND (sku = ? OR attributes LIKE ?)
                  ORDER BY (sku = ?) DESC, in_stock DESC, name LIMIT ' . $limit,
                array_merge($params, [$query, '%' . $query . '%', $query])
            );
            if ($exact !== []) {
                return $exact;
            }
        }

        if ($query === '') {
            return Database::all(
                'SELECT * FROM products WHERE ' . $where . ' ORDER BY in_stock DESC, name LIMIT ' . $limit,
                $params
            );
        }

        $rows = self::fulltext($where, $params, $query, $limit);
        if ($rows === []) {
            $rows = self::like($where, $params, $query, $limit);
        }
        return $rows;
    }

    /** Compact rows the model can read cheaply. */
    public static function forTool(array $rows): array
    {
        return array_map(static function (array $row): array {
            $item = [
                'ref' => 'p' . $row['id'],
                'name' => $row['name'],
                'price' => self::price($row),
                'in_stock' => (bool)$row['in_stock'],
                'url' => $row['permalink'] ?: null,
            ];
            if ((int)$row['on_sale'] === 1 && $row['regular_price'] !== null) {
                $item['regular_price'] = self::money((float)$row['regular_price'], $row);
            }
            foreach (['sku' => 'sku', 'stock_text' => 'stock', 'brands' => 'brand', 'categories' => 'categories', 'attributes' => 'details'] as $column => $key) {
                if (!empty($row[$column])) {
                    $item[$key] = mb_substr((string)$row[$column], 0, 160);
                }
            }
            if (!empty($row['summary'])) {
                $item['summary'] = mb_substr((string)$row['summary'], 0, 200);
            }
            return $item;
        }, $rows);
    }

    /** What the widget needs to draw a product card. */
    public static function forCard(array $row): array
    {
        $card = [
            'name' => $row['name'],
            'price' => self::price($row),
            'in_stock' => (bool)$row['in_stock'],
            'stock' => $row['stock_text'] ?: ((int)$row['in_stock'] === 1 ? 'In stock' : 'Out of stock'),
            'url' => $row['permalink'] ?: null,
            'image' => $row['image'] ?: null,
        ];
        if ((int)$row['on_sale'] === 1 && $row['regular_price'] !== null && (float)$row['regular_price'] > (float)$row['price']) {
            $card['was'] = self::money((float)$row['regular_price'], $row);
        }
        return $card;
    }

    public static function price(array $row): string
    {
        if ($row['price'] === null) {
            return 'price on request';
        }
        $price = self::money((float)$row['price'], $row);
        if ($row['max_price'] !== null && (float)$row['max_price'] > (float)$row['price']) {
            return $price . ' – ' . self::money((float)$row['max_price'], $row);
        }
        return $price;
    }

    private static function money(float $amount, array $row): string
    {
        return ($row['currency_prefix'] ?? '$') . number_format($amount, 2) . ($row['currency_suffix'] ?? '');
    }

    /** @return array{0:string, 1:array} */
    private static function filters(int $siteId, array $filters): array
    {
        $where = 'site_id = ?';
        $params = [$siteId];

        if (!empty($filters['in_stock_only'])) {
            $where .= ' AND in_stock = 1';
        }
        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $where .= ' AND price IS NOT NULL AND price <= ?';
            $params[] = (float)$filters['max_price'];
        }
        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $where .= ' AND price IS NOT NULL AND price >= ?';
            $params[] = (float)$filters['min_price'];
        }
        foreach (['category' => ['categories', 'tags'], 'brand' => ['brands']] as $key => $columns) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $where .= ' AND (' . implode(' OR ', array_map(static fn (string $c): string => $c . ' LIKE ?', $columns)) . ')';
            foreach ($columns as $unused) {
                $params[] = '%' . $value . '%';
            }
        }
        return [$where, $params];
    }

    private static function fulltext(string $where, array $params, string $query, int $limit): array
    {
        try {
            return Database::all(
                'SELECT *, MATCH(name, sku, search_text) AGAINST (?) AS score
                   FROM products
                  WHERE ' . $where . ' AND MATCH(name, sku, search_text) AGAINST (?)
                  ORDER BY in_stock DESC, score DESC
                  LIMIT ' . $limit,
                array_merge([$query], $params, [$query])
            );
        } catch (\Throwable $e) {
            return []; // no full-text index on this server
        }
    }

    /** Ranks rows by how many search words they contain, name hits weighted higher. */
    private static function like(string $where, array $params, string $query, int $limit): array
    {
        $terms = array_values(array_unique(array_filter(
            preg_split('/[\s,]+/u', mb_strtolower($query)) ?: [],
            static fn (string $term): bool => mb_strlen($term) >= 2
        )));
        if ($terms === []) {
            return [];
        }
        $terms = array_slice($terms, 0, 8);

        $score = [];
        $scoreParams = [];
        $match = [];
        $matchParams = [];
        foreach ($terms as $term) {
            $like = '%' . $term . '%';
            $score[] = '(name LIKE ?) * 3 + (sku LIKE ?) * 3 + (search_text LIKE ?)';
            array_push($scoreParams, $like, $like, $like);
            $match[] = '(name LIKE ? OR sku LIKE ? OR search_text LIKE ?)';
            array_push($matchParams, $like, $like, $like);
        }

        return Database::all(
            'SELECT *, (' . implode(' + ', $score) . ') AS score
               FROM products
              WHERE ' . $where . ' AND (' . implode(' OR ', $match) . ')
              ORDER BY score DESC, in_stock DESC, name
              LIMIT ' . $limit,
            array_merge($scoreParams, $params, $matchParams)
        );
    }
}
