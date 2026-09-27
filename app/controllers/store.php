<?php
/**
 * JSON endpoints for website sync and the product list, driven step by step
 * from the Knowledge base tab. Session-authenticated and CSRF-checked by the
 * front controller.
 */
declare(strict_types=1);

use App\Auth;
use App\ProductSearch;
use App\Site;
use App\StoreSync;

$site = Site::find((int)(post('site_id') ?? query('site_id') ?? 0), Auth::id());
if (!$site) {
    json_out(['error' => 'That website was not found.'], 404);
}

$action = (string)(post('action') ?? query('action') ?? '');
@set_time_limit(120);

try {
    switch ($action) {
        case 'start':
            $settings = StoreSync::settings($site);
            $settings['url'] = StoreSync::normalizeUrl((string)post('url', ''));
            foreach (StoreSync::PHASES as $phase) {
                $settings[$phase] = post($phase) === '1' ? 1 : 0;
            }
            $phases = StoreSync::phases($settings);
            if ($phases === []) {
                json_out(['error' => 'Choose at least one of pages, posts or products.'], 422);
            }
            StoreSync::saveSettings((int)$site['id'], $settings);
            json_out(['ok' => true, 'token' => StoreSync::newToken(), 'phases' => $phases, 'url' => $settings['url']]);

        case 'step':
            $result = StoreSync::step(
                Site::find((int)$site['id']) ?? $site,
                (string)post('token', ''),
                (string)post('phase', ''),
                (int)post('page', '1')
            );
            json_out(['ok' => true] + $result);

        case 'finish':
            $completed = array_values(array_intersect(StoreSync::PHASES, (array)($_POST['completed'] ?? [])));
            $stats = StoreSync::finish(Site::find((int)$site['id']) ?? $site, (string)post('token', ''), $completed);
            json_out(['ok' => true, 'stats' => $stats]);

        case 'fail':
            StoreSync::fail($site, (string)post('message', 'Sync stopped.'));
            json_out(['ok' => true]);

        case 'products':
            $query = (string)(query('q') ?? post('q') ?? '');
            $rows = ProductSearch::search((int)$site['id'], $query, [], 25);
            json_out([
                'ok' => true,
                'total' => ProductSearch::count((int)$site['id']),
                'products' => array_map(static fn (array $row): array => [
                    'name' => $row['name'],
                    'sku' => $row['sku'],
                    'price' => ProductSearch::price($row),
                    'in_stock' => (bool)$row['in_stock'],
                    'stock' => $row['stock_text'],
                    'url' => $row['permalink'],
                ], $rows),
            ]);

        default:
            json_out(['error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    error_log('[chatbot] store sync: ' . $e->getMessage());
    json_out(['error' => $e->getMessage()], 422);
}
