<?php
declare(strict_types=1);

use App\Auth;
use App\Database;
use App\KnowledgeBase;
use App\Scraper;
use App\Session;
use App\Site;

$userId = Auth::id();
$siteId = (int)(query('id') ?? post('id') ?? 0);
$site = Site::find($siteId, $userId);

if (!$site) {
    Session::flash('error', 'That website was not found.');
    redirect(admin_url('sites'));
}

$back = admin_url('site', ['id' => $siteId, 'tab' => 'knowledge']);

// Knowledge lives in the website's Knowledge base tab; this route only handles
// the form posts behind it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($back);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    try {
        if ($action === 'save_text') {
            $documentId = (int)post('document_id', '0') ?: null;
            $id = KnowledgeBase::saveDocument($siteId, [
                'title' => post('title'),
                'content' => post('content'),
                'source_type' => 'text',
            ], $documentId);
            KnowledgeBase::indexDocument($id, $site);
            Session::flash('success', 'Saved and indexed.');
            redirect($back);
        }

        if ($action === 'import_url') {
            $url = (string)post('url', '');
            $page = Scraper::fetch($url);
            if (trim($page['text']) === '') {
                throw new RuntimeException('No readable text was found on that page.');
            }
            $id = KnowledgeBase::saveDocument($siteId, [
                'title' => post('title') ?: $page['title'],
                'content' => $page['text'],
                'source_type' => 'url',
                'source_url' => $url,
            ]);
            KnowledgeBase::indexDocument($id, $site);
            Session::flash('success', 'Page imported and indexed.');
            redirect($back);
        }

        if ($action === 'upload') {
            $file = $_FILES['file'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Choose a file to upload.');
            }
            $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, Scraper::ALLOWED_UPLOAD_EXTENSIONS, true)) {
                throw new RuntimeException('Supported files: ' . implode(', ', Scraper::ALLOWED_UPLOAD_EXTENSIONS) . '.');
            }
            if ((int)$file['size'] > 2 * 1024 * 1024) {
                throw new RuntimeException('Files must be 2 MB or smaller.');
            }
            $text = Scraper::fromUpload((string)$file['tmp_name'], (string)$file['name']);
            if (trim($text) === '') {
                throw new RuntimeException('That file contains no readable text.');
            }
            $id = KnowledgeBase::saveDocument($siteId, [
                'title' => post('title') ?: pathinfo((string)$file['name'], PATHINFO_FILENAME),
                'content' => $text,
                'source_type' => 'file',
                'source_url' => (string)$file['name'],
            ]);
            KnowledgeBase::indexDocument($id, $site);
            Session::flash('success', 'File imported and indexed.');
            redirect($back);
        }

        if ($action === 'reindex') {
            $ids = post('document_id')
                ? [(int)post('document_id')]
                : array_column(Database::all('SELECT id FROM documents WHERE site_id = ?', [$siteId]), 'id');
            foreach ($ids as $id) {
                KnowledgeBase::indexDocument((int)$id, $site);
            }
            Session::flash('success', count($ids) . ' document(s) re-indexed.');
            redirect($back);
        }

        if ($action === 'delete') {
            KnowledgeBase::deleteDocument($siteId, (int)post('document_id'));
            Session::flash('success', 'Document deleted.');
            redirect($back);
        }

        if ($action === 'test') {
            $question = (string)post('question', '');
            Session::set('kb_test', [
                'question' => $question,
                'results' => KnowledgeBase::search(
                    $site,
                    $question,
                    (int)$site['ai']['top_k'],
                    (float)$site['ai']['min_score']
                ),
            ]);
            redirect($back);
        }
    } catch (Throwable $e) {
        Session::flash('error', $e->getMessage());
        redirect($back);
    }
}

redirect($back);
