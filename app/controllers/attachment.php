<?php
declare(strict_types=1);

use App\Auth;
use App\Database;
use App\Session;
use App\Uploads;

$attachment = Database::first(
    'SELECT a.*, s.user_id FROM attachments a JOIN sites s ON s.id = a.site_id WHERE a.id = ? LIMIT 1',
    [(int)(query('id') ?? 0)]
);

if (!$attachment || (int)$attachment['user_id'] !== Auth::id()) {
    Session::flash('error', 'That file was not found.');
    redirect(admin_url('conversations'));
}

$path = Uploads::path($attachment);
if (!is_file($path)) {
    Session::flash('error', 'That file is no longer on the server.');
    redirect(admin_url('conversations'));
}

$inline = Uploads::isImage($attachment) || $attachment['mime'] === 'application/pdf';

header('Content-Type: ' . $attachment['mime']);
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header(sprintf(
    'Content-Disposition: %s; filename="%s"',
    $inline ? 'inline' : 'attachment',
    preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$attachment['original_name'])
));
readfile($path);
