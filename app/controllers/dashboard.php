<?php
declare(strict_types=1);

use App\Auth;
use App\Database;
use App\Settings;
use App\Site;
use App\View;

$userId = Auth::id();
$sites = Site::forUser($userId);
$siteIds = array_column($sites, 'id');

$stats = ['conversations' => 0, 'messages' => 0, 'documents' => 0, 'today' => 0];
$recent = [];

if ($siteIds !== []) {
    $in = implode(',', array_fill(0, count($siteIds), '?'));
    $stats['conversations'] = (int)Database::value("SELECT COUNT(*) FROM conversations WHERE site_id IN ($in)", $siteIds);
    $stats['messages'] = (int)Database::value("SELECT COUNT(*) FROM messages WHERE site_id IN ($in)", $siteIds);
    $stats['documents'] = (int)Database::value("SELECT COUNT(*) FROM documents WHERE site_id IN ($in)", $siteIds);
    $stats['today'] = (int)Database::value(
        "SELECT COUNT(*) FROM messages WHERE site_id IN ($in) AND created_at >= CURDATE()",
        $siteIds
    );
    $recent = Database::all(
        "SELECT c.*, s.name AS site_name
           FROM conversations c JOIN sites s ON s.id = c.site_id
          WHERE c.site_id IN ($in)
          ORDER BY c.last_activity_at DESC LIMIT 8",
        $siteIds
    );
}

View::display('admin.dashboard', [
    'sites' => $sites,
    'stats' => $stats,
    'recent' => $recent,
    'hasKey' => Settings::openAiKey() !== '',
]);
