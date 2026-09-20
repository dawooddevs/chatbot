<?php
declare(strict_types=1);

namespace App;

final class Config
{
    private static array $items = [];

    public static function load(array $items): void
    {
        self::$items = $items;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$items[$key] ?? $default;
    }

    public static function all(): array
    {
        return self::$items;
    }

    public static function isInstalled(): bool
    {
        return self::$items !== [] && !empty(self::$items['db']['name']);
    }
}
