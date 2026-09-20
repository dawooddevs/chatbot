<?php
/**
 * Public chat endpoint used by the widget. CORS is granted only to the
 * domains configured for the site key.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\ChatService;
use App\RateLimiter;
use App\Site;

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');

function cors(string $origin): void
{
    if ($origin !== '') {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 86400');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    cors($origin);
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    cors($origin);
    json_out(['error' => 'Method not allowed.'], 405);
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$key = trim((string)($payload['key'] ?? ''));
$message = trim((string)($payload['message'] ?? ''));

$site = $key !== '' ? Site::findByKey($key) : null;

if (!$site) {
    cors($origin);
    json_out(['error' => 'Unknown site key.'], 404);
}

if (!Site::allowsOrigin($site, $origin !== '' ? $origin : (string)($_SERVER['HTTP_REFERER'] ?? ''))) {
    json_out(['error' => 'This chatbot is not enabled for this domain.'], 403);
}

cors($origin);

if ($site['status'] !== 'active') {
    json_out(['error' => 'This chatbot is currently paused.'], 403);
}

if ($message === '') {
    json_out(['error' => 'Please type a message.'], 422);
}
if (mb_strlen($message) > 2000) {
    $message = mb_substr($message, 0, 2000);
}

$ipHash = ChatService::hashIp((string)($_SERVER['REMOTE_ADDR'] ?? ''));
$limit = (int)$site['ai']['rate_limit_per_hour'];
if (!RateLimiter::hit('chat:' . $site['id'] . ':' . substr($ipHash, 0, 24), $limit, 3600)) {
    json_out(['error' => 'You have reached the message limit for now. Please try again later.'], 429);
}

try {
    $result = ChatService::reply($site, $message, [
        'conversation_id' => (int)($payload['conversation_id'] ?? 0),
        'visitor_id' => (string)($payload['visitor_id'] ?? ''),
        'page_url' => (string)($payload['page_url'] ?? ''),
        'referrer' => (string)($payload['referrer'] ?? ''),
    ]);
} catch (Throwable $e) {
    error_log('[chatbot] ' . $e->getMessage());
    json_out(['error' => $site['ai']['fallback']], 200);
}

json_out([
    'reply' => $result['reply'],
    'conversation_id' => $result['conversation_id'],
]);
