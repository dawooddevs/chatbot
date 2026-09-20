<?php
/**
 * Public chat endpoint used by the widget. CORS is granted only to the
 * domains configured for the site key (plus the panel's own host).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\ChatService;
use App\PublicApi;

[$site, $payload] = PublicApi::begin('chat');

$message = trim((string)($payload['message'] ?? ''));
$attachmentId = (int)($payload['attachment_id'] ?? 0);

if ($message === '' && $attachmentId === 0) {
    json_out(['error' => 'Please type a message.'], 422);
}
if (mb_strlen($message) > 2000) {
    $message = mb_substr($message, 0, 2000);
}

try {
    $result = ChatService::reply($site, $message, [
        'conversation_id' => (int)($payload['conversation_id'] ?? 0),
        'visitor_id' => (string)($payload['visitor_id'] ?? ''),
        'page_url' => (string)($payload['page_url'] ?? ''),
        'referrer' => (string)($payload['referrer'] ?? ''),
        'attachment_id' => $attachmentId,
    ]);
} catch (Throwable $e) {
    error_log('[chatbot] ' . $e->getMessage());
    json_out(['error' => $site['ai']['fallback']], 200);
}

json_out([
    'reply' => $result['reply'],
    'conversation_id' => $result['conversation_id'],
]);
