<?php
/**
 * Re-syncs every website that has a store address set.
 *
 * cPanel -> Cron Jobs, e.g. hourly:
 *   php /home/USER/chatbot.dawood.top/bin/sync.php
 * One website only:
 *   php /home/USER/chatbot.dawood.top/bin/sync.php --site=3
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Database;
use App\Site;
use App\StoreSync;

set_time_limit(0);

$only = null;
foreach ($argv as $arg) {
    if (preg_match('/^--site=(\d+)$/', $arg, $m)) {
        $only = (int)$m[1];
    }
}

$rows = Database::all("SELECT id FROM sites WHERE status = 'active' AND store IS NOT NULL AND store <> ''");
$failures = 0;

foreach ($rows as $row) {
    if ($only !== null && (int)$row['id'] !== $only) {
        continue;
    }
    $site = Site::find((int)$row['id']);
    $settings = StoreSync::settings($site);
    if ($settings['url'] === '' || StoreSync::phases($settings) === []) {
        continue;
    }

    $started = microtime(true);
    echo '[' . date('c') . '] ' . $site['name'] . ' <- ' . $settings['url'] . PHP_EOL;
    try {
        $stats = StoreSync::runAll($site, static function (string $line): void {
            echo '  ' . $line . PHP_EOL;
        });
        printf(
            "  done in %.1fs: %d products, %d pages, %d posts%s",
            microtime(true) - $started,
            $stats['products'],
            $stats['pages'],
            $stats['posts'],
            PHP_EOL
        );
    } catch (Throwable $e) {
        $failures++;
        StoreSync::fail($site, $e->getMessage());
        echo '  failed: ' . $e->getMessage() . PHP_EOL;
    }
}

exit($failures > 0 ? 1 : 0);
