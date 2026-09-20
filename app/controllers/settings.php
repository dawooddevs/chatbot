<?php
declare(strict_types=1);

use App\OpenAi;
use App\RateLimiter;
use App\Session;
use App\Settings;
use App\View;

$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'save') {
        $key = (string)post('openai_api_key', '');
        // An empty field keeps the stored key instead of wiping it.
        if ($key !== '' && !str_contains($key, '•')) {
            Settings::put('openai_api_key', $key);
        }
        Settings::put('openai_base_url', rtrim((string)post('openai_base_url', 'https://api.openai.com/v1'), '/'));
        Settings::put('default_chat_model', array_key_exists((string)post('default_chat_model'), OpenAi::CHAT_MODELS)
            ? (string)post('default_chat_model') : 'gpt-4o-mini');
        Settings::put('platform_name', mb_substr((string)post('platform_name', 'Chatbot Platform'), 0, 60));
        Session::flash('success', 'Settings saved.');
        redirect(admin_url('settings'));
    }

    if ($action === 'clear_key') {
        Settings::put('openai_api_key', '');
        Session::flash('warning', 'API key removed.');
        redirect(admin_url('settings'));
    }

    if ($action === 'test') {
        try {
            $testResult = ['ok' => true, 'message' => 'Connected. Model replied: ' . (new OpenAi())->ping()];
        } catch (Throwable $e) {
            $testResult = ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    if ($action === 'prune') {
        RateLimiter::prune();
        Session::flash('success', 'Old rate-limit rows cleared.');
        redirect(admin_url('settings'));
    }
}

$key = Settings::openAiKey();

View::display('admin.settings', [
    'hasKey' => $key !== '',
    'maskedKey' => $key !== '' ? substr($key, 0, 6) . str_repeat('•', 24) . substr($key, -4) : '',
    'fromEnv' => is_string(getenv('OPENAI_API_KEY')) && getenv('OPENAI_API_KEY') !== '',
    'baseUrl' => Settings::get('openai_base_url', 'https://api.openai.com/v1'),
    'defaultModel' => Settings::get('default_chat_model', 'gpt-4o-mini'),
    'platformName' => Settings::get('platform_name', 'Chatbot Platform'),
    'testResult' => $testResult,
]);
