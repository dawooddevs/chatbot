<?php
/**
 * JSON endpoints behind the FAQ manager in the design tab.
 * Session-authenticated and CSRF-checked by the front controller.
 */
declare(strict_types=1);

use App\Auth;
use App\Faq;
use App\Site;

$site = Site::find((int)(post('site_id') ?? query('site_id') ?? 0), Auth::id());

if (!$site) {
    json_out(['error' => 'That website was not found.'], 404);
}

$siteId = (int)$site['id'];
$action = (string)(post('action') ?? query('action') ?? 'list');

function faq_payload(array $site): array
{
    $siteId = (int)$site['id'];
    return [
        'ok' => true,
        'faqs' => array_map(static fn (array $faq): array => [
            'id' => (int)$faq['id'],
            'question' => $faq['question'],
            'answer' => $faq['answer'],
            'show_as_chip' => (int)$faq['show_as_chip'] === 1,
        ], Faq::forSite($siteId)),
        'chips' => Faq::chips($siteId),
        'max_chips' => Faq::MAX_CHIPS,
    ];
}

try {
    switch ($action) {
        case 'create':
            $question = trim((string)post('question', ''));
            $answer = trim((string)post('answer', ''));
            if ($question === '' || $answer === '') {
                json_out(['error' => 'Both a question and an answer are needed.'], 422);
            }
            Faq::create($siteId, $question, $answer, post('show_as_chip') === '1');
            Faq::syncDocument($site);
            break;

        case 'update':
            $id = (int)post('id');
            if (!Faq::find($id, $siteId)) {
                json_out(['error' => 'That question no longer exists.'], 404);
            }
            $question = trim((string)post('question', ''));
            $answer = trim((string)post('answer', ''));
            if ($question === '' || $answer === '') {
                json_out(['error' => 'Both a question and an answer are needed.'], 422);
            }
            Faq::update($id, $siteId, $question, $answer, post('show_as_chip') === '1');
            Faq::syncDocument($site);
            break;

        case 'delete':
            Faq::delete((int)post('id'), $siteId);
            Faq::syncDocument($site);
            break;

        case 'reorder':
            $ids = array_map('intval', (array)($_POST['ids'] ?? []));
            Faq::reorder($siteId, $ids);
            Faq::syncDocument($site);
            break;

        case 'list':
        default:
            break;
    }
} catch (Throwable $e) {
    error_log('[chatbot] faq: ' . $e->getMessage());
    json_out(['error' => 'That change could not be saved.'], 500);
}

json_out(faq_payload($site));
