<?php
declare(strict_types=1);

namespace App;

/**
 * Fixed-window limiter backed by the rate_limits table, so it also works
 * on shared hosting where APCu/Redis are not available.
 */
final class RateLimiter
{
    public static function hit(string $key, int $limit, int $windowSeconds): bool
    {
        $now = time();
        $windowStart = $now - ($now % $windowSeconds);
        $bucket = substr($key, 0, 191);

        Database::run(
            'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE
                hits = IF(window_start = VALUES(window_start), hits + 1, 1),
                window_start = VALUES(window_start)',
            [$bucket, $windowStart]
        );

        $hits = (int)Database::value('SELECT hits FROM rate_limits WHERE bucket = ?', [$bucket]);
        return $hits <= $limit;
    }

    public static function prune(int $olderThanSeconds = 86400): void
    {
        Database::run('DELETE FROM rate_limits WHERE window_start < ?', [time() - $olderThanSeconds]);
    }
}
