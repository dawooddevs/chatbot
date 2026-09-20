<?php
declare(strict_types=1);

use App\Auth;
use App\Database;
use App\Session;
use App\View;

$id = (int)(query('id') ?? 0);
$conversation = Database::first(
    'SELECT c.*, s.name AS site_name, s.user_id
       FROM conversations c JOIN sites s ON s.id = c.site_id
      WHERE c.id = ? LIMIT 1',
    [$id]
);

if (!$conversation || (int)$conversation['user_id'] !== Auth::id()) {
    Session::flash('error', 'Conversation not found.');
    redirect(admin_url('conversations'));
}

View::display('admin.conversation', [
    'conversation' => $conversation,
    'messages' => Database::all(
        'SELECT m.*, a.original_name, a.mime, a.size_bytes
           FROM messages m
           LEFT JOIN attachments a ON a.id = m.attachment_id
          WHERE m.conversation_id = ?
          ORDER BY m.id',
        [$id]
    ),
]);
