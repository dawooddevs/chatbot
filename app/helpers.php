<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Absolute path to install.php before any configuration exists.
 * It is worked out from the document root so it stays correct whether the app
 * sits on a domain root or in a sub-folder, and it always starts with a single
 * slash - a doubled one would be read by browsers as a protocol-relative host.
 */
function install_url(): string
{
    $base = '';
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string)$_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(APP_ROOT);

    if ($documentRoot !== false && $appRoot !== false && str_starts_with($appRoot, $documentRoot)) {
        $base = substr($appRoot, strlen($documentRoot));
    } else {
        $base = dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'));
        if (basename($base) === 'api') {
            $base = dirname($base);
        }
    }

    $base = rtrim(str_replace('\\', '/', $base), '/');
    if ($base !== '' && $base[0] !== '/') {
        $base = '/' . $base;
    }

    return $base . '/install.php';
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

function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / (1024 * 1024), 1) . ' MB';
}
