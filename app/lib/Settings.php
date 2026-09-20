<?php
declare(strict_types=1);

namespace App;

/**
 * Global key/value settings (OpenAI credentials, defaults).
 */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $rows = Database::all('SELECT name, value FROM settings');
        $out = [];
        foreach ($rows as $row) {
            $out[$row['name']] = $row['value'];
        }
        return self::$cache = $out;
    }

    public static function get(string $name, ?string $default = null): ?string
    {
        $value = self::all()[$name] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    public static function put(string $name, ?string $value): void
    {
        Database::run(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, $value]
        );
        self::$cache = null;
    }

    public static function openAiKey(): string
    {
        $key = getenv('OPENAI_API_KEY');
        if (is_string($key) && $key !== '') {
            return $key;
        }
        return (string)self::get('openai_api_key', '');
    }
}
