<?php
/**
 * Serves the widget loader for one site:
 *   <script src="https://example.com/chatbot/embed.php?k=SITE_KEY" async></script>
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Site;

header('Content-Type: application/javascript; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300');

$key = (string)(query('k') ?? '');
$site = $key !== '' ? Site::findByKey($key) : null;

if (!$site || $site['status'] !== 'active') {
    echo "/* chatbot: unknown or paused site key */\n";
    exit;
}

$config = [
    'key' => $site['site_key'],
    'endpoint' => base_url('api/chat.php'),
    'uploadEndpoint' => base_url('api/upload.php'),
    'transcribeEndpoint' => base_url('api/transcribe.php'),
    'name' => $site['name'],
    'design' => $site['design'],
];

echo 'window.ChatbotWidgetConfig=' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n";
readfile(__DIR__ . '/assets/widget.js');
