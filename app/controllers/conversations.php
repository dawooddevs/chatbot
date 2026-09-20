<?php
declare(strict_types=1);

use App\Auth;
use App\Database;
use App\Session;
use App\Site;
use App\View;

$userId = Auth::id();
$sites = Site::forUser($userId);
$siteIds = array_column($sites, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete' && $siteIds !== []) {
    $id = (int)post('conversation_id');
    $in = implode(',', array_fill(0, count($siteIds), '?'));
    $owned = Database::value("SELECT id FROM conversations WHERE id = ? AND site_id IN ($in)", [$id, ...$siteIds]);
    if ($owned !== null) {
        Database::run('DELETE FROM messages WHERE conversation_id = ?', [$id]);
        Database::run('DELETE FROM conversations WHERE id = ?', [$id]);
        Session::flash('success', 'Conversation deleted.');
    }
    redirect(admin_url('conversations'));
}

$filterSite = (int)(query('site') ?? 0);
$conversations = [];
$pages = 1;
$page = max(1, int_query('page', 1));
$perPage = 25;

if ($siteIds !== []) {
    $in = implode(',', array_fill(0, count($siteIds), '?'));
    $params = $siteIds;
    $where = "c.site_id IN ($in)";
    if ($filterSite && in_array($filterSite, array_map('intval', $siteIds), true)) {
        $where .= ' AND c.site_id = ?';
        $params[] = $filterSite;
    }
    $total = (int)Database::value("SELECT COUNT(*) FROM conversations c WHERE $where", $params);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $conversations = Database::all(
        "SELECT c.*, s.name AS site_name,
                (SELECT content FROM messages m WHERE m.conversation_id = c.id AND m.role = 'user' ORDER BY m.id LIMIT 1) AS first_message
           FROM conversations c JOIN sites s ON s.id = c.site_id
          WHERE $where
          ORDER BY c.last_activity_at DESC
          LIMIT $perPage OFFSET $offset",
        $params
    );
}

View::display('admin.conversations', [
    'sites' => $sites,
    'conversations' => $conversations,
    'filterSite' => $filterSite,
    'page' => $page,
    'pages' => $pages,
]);
