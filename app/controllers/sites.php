<?php
declare(strict_types=1);

use App\Auth;
use App\Database;
use App\Session;
use App\Site;
use App\View;

$userId = Auth::id();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'create') {
        $name = (string)post('name', '');
        if ($name === '') {
            $errors[] = 'Give the website a name.';
        }
        if ($errors === []) {
            $id = Database::insert(
                'INSERT INTO sites (user_id, name, site_key, allowed_domains, status, design, ai, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $userId,
                    $name,
                    Site::newKey(),
                    implode("\n", Site::domains((string)post('allowed_domains', ''))),
                    'active',
                    json_encode(Site::DESIGN_DEFAULTS),
                    json_encode(Site::AI_DEFAULTS),
                ]
            );
            Session::flash('success', 'Website created. Add its knowledge next, then copy the embed code.');
            redirect(admin_url('site', ['id' => $id]));
        }
    }

    if ($action === 'delete') {
        $siteId = (int)post('id');
        $site = Site::find($siteId, $userId);
        if ($site) {
            Database::run('DELETE FROM chunks WHERE site_id = ?', [$siteId]);
            Database::run('DELETE FROM documents WHERE site_id = ?', [$siteId]);
            Database::run('DELETE FROM messages WHERE site_id = ?', [$siteId]);
            Database::run('DELETE FROM conversations WHERE site_id = ?', [$siteId]);
            Database::run('DELETE FROM sites WHERE id = ? AND user_id = ?', [$siteId, $userId]);
            Session::flash('success', 'Website and all of its data were deleted.');
        }
        redirect(admin_url('sites'));
    }
}

$sites = Site::forUser($userId);
$counts = [];
foreach ($sites as $site) {
    $counts[$site['id']] = [
        'documents' => (int)Database::value('SELECT COUNT(*) FROM documents WHERE site_id = ?', [$site['id']]),
        'conversations' => (int)Database::value('SELECT COUNT(*) FROM conversations WHERE site_id = ?', [$site['id']]),
    ];
}

View::display('admin.sites', ['sites' => $sites, 'counts' => $counts, 'errors' => $errors]);
