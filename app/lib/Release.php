<?php
declare(strict_types=1);

namespace App;

/**
 * Reads the VERSION file written by the deployment pipeline, and tracks
 * whether the panel has acknowledged the current build.
 */
final class Release
{
    private const DISMISSED_KEY = 'deploy_notice_dismissed';

    /** @return array{code:string, subject:string, deployed_at:string}|null */
    public static function current(): ?array
    {
        $path = APP_ROOT . '/VERSION';
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data) || empty($data['code'])) {
            return null;
        }
        return [
            'code' => (string)$data['code'],
            'subject' => (string)($data['subject'] ?? ''),
            'deployed_at' => (string)($data['deployed_at'] ?? ''),
        ];
    }

    /** The release to announce, or null when there is nothing new to show. */
    public static function unacknowledged(): ?array
    {
        $release = self::current();
        if ($release === null) {
            return null;
        }
        return Settings::get(self::DISMISSED_KEY) === $release['code'] ? null : $release;
    }

    /** Dismissing stores the code, so the next deployment shows its own notice. */
    public static function acknowledge(string $code): void
    {
        Settings::put(self::DISMISSED_KEY, $code);
    }
}
