<?php
/**
 * Visitor file attachments. Files land in storage/uploads, which is not
 * web readable; the panel serves them back through an authenticated route.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\PublicApi;
use App\Uploads;

[$site, $payload] = PublicApi::begin('upload', 30);

if (empty($site['design']['show_attachments'])) {
    json_out(['error' => 'Attachments are turned off for this chatbot.'], 403);
}

$file = $_FILES['file'] ?? null;
if (!is_array($file)) {
    json_out(['error' => 'No file was received.'], 422);
}

try {
    $stored = Uploads::storeAttachment($file, (int)$site['id'], (int)($payload['conversation_id'] ?? 0) ?: null);
} catch (Throwable $e) {
    json_out(['error' => $e->getMessage()], 422);
}

json_out([
    'attachment_id' => $stored['id'],
    'name' => $stored['name'],
    'size' => $stored['size'],
]);
