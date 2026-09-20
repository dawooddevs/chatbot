<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(string $path = ''): string
{
    $base = rtrim((string)App\Config::get('base_url', ''), '/');
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function admin_url(string $route = '', array $params = []): string
{
    $url = base_url('index.php');
    if ($route !== '') {
        $params = ['r' => $route] + $params;
    }
    return $params ? $url . '?' . http_build_query($params) : $url;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function post(string $key, ?string $default = null): ?string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function query(string $key, ?string $default = null): ?string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function int_query(string $key, int $default = 0): int
{
    return (int)($_GET[$key] ?? $default);
}

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function str_limit(string $value, int $limit = 80): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit - 1) . '…';
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    foreach ([[31536000, 'y'], [2592000, 'mo'], [604800, 'w'], [86400, 'd'], [3600, 'h'], [60, 'm']] as [$secs, $label]) {
        if ($diff >= $secs) {
            return intdiv($diff, $secs) . $label . ' ago';
        }
    }
    return 'just now';
}

function color_or(string $value, string $fallback): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : $fallback;
}
