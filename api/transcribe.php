<?php
/**
 * Turns a recorded voice note into text so the visitor can send it as a message.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\OpenAi;
use App\PublicApi;
use App\Settings;

[$site] = PublicApi::begin('voice', 30);

if (empty($site['design']['show_voice'])) {
    json_out(['error' => 'Voice messages are turned off for this chatbot.'], 403);
}

$file = $_FILES['audio'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_out(['error' => 'No recording was received.'], 422);
}
if ((int)$file['size'] > 10 * 1024 * 1024) {
    json_out(['error' => 'That recording is too long.'], 422);
}

try {
    $text = (new OpenAi())->transcribe(
        (string)$file['tmp_name'],
        'voice.' . (str_contains((string)($file['type'] ?? ''), 'mp4') ? 'mp4' : 'webm'),
        (string)Settings::get('transcription_model', 'whisper-1')
    );
} catch (Throwable $e) {
    error_log('[chatbot] transcription: ' . $e->getMessage());
    json_out(['error' => 'The recording could not be transcribed.'], 502);
}

json_out(['text' => $text]);
