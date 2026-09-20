<?php
declare(strict_types=1);

namespace App;

/**
 * Shared front door for the endpoints the widget calls: CORS, site lookup,
 * domain allow-list and rate limiting in one place.
 */
final class PublicApi
{
    /**
     * @return array{0: array, 1: array} the site and the decoded request payload
     */
    public static function begin(string $rateKey, ?int $limitOverride = null): array
    {
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $method = (string)($_SERVER['REQUEST_METHOD'] ?? '');

        if ($method === 'OPTIONS') {
            self::cors($origin);
            http_response_code(204);
            exit;
        }
        if ($method !== 'POST') {
            self::cors($origin);
            json_out(['error' => 'Method not allowed.'], 405);
        }

        $payload = self::payload();
        $key = trim((string)($payload['key'] ?? ''));
        $site = $key !== '' ? Site::findByKey($key) : null;

        if (!$site) {
            self::cors($origin);
            json_out(['error' => 'Unknown site key.'], 404);
        }
        if (!Site::allowsOrigin($site, $origin !== '' ? $origin : (string)($_SERVER['HTTP_REFERER'] ?? ''))) {
            json_out(['error' => 'This chatbot is not enabled for this domain.'], 403);
        }

        self::cors($origin);

        if ($site['status'] !== 'active') {
            json_out(['error' => 'This chatbot is currently paused.'], 403);
        }

        $limit = $limitOverride ?? (int)$site['ai']['rate_limit_per_hour'];
        $ipHash = ChatService::hashIp((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!RateLimiter::hit($rateKey . ':' . $site['id'] . ':' . substr($ipHash, 0, 24), $limit, 3600)) {
            json_out(['error' => 'You have reached the limit for now. Please try again later.'], 429);
        }

        return [$site, $payload];
    }

    public static function cors(string $origin): void
    {
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');
    }

    /** JSON bodies for chat, multipart for uploads. */
    private static function payload(): array
    {
        $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string)file_get_contents('php://input'), true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }
}
