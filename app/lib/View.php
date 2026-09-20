<?php
declare(strict_types=1);

namespace App;

final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture($layout, $data + ['content' => $content]);
    }

    public static function display(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        echo self::render($template, $data, $layout);
    }

    private static function capture(string $template, array $data): string
    {
        $path = APP_DIR . '/views/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException('View not found: ' . $template);
        }
        extract(self::$shared, EXTR_SKIP);
        extract($data, EXTR_OVERWRITE);
        ob_start();
        require $path;
        return (string)ob_get_clean();
    }
}
