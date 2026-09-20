<?php
/**
 * Copy of the file the installer writes to app/config.php.
 * Keep app/config.php out of version control - it holds live credentials.
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'cpaneluser_chatbot',
        'user' => 'cpaneluser_chatbot',
        'pass' => '',
        'port' => 3306,
    ],
    // Public URL of this installation, no trailing slash.
    // e.g. https://example.com/chatbot
    'base_url' => 'https://example.com/chatbot',
    // 32+ random bytes, used for signing cookies and hashing visitor IPs.
    'app_key' => '',
    'session_name' => 'chatbotsid',
    'debug' => false,
];
