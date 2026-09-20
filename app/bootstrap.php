<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = APP_DIR . '/lib/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require APP_DIR . '/helpers.php';

if (!is_file(APP_DIR . '/config.php')) {
    if (PHP_SAPI !== 'cli' && !defined('INSTALLER')) {
        header('Location: ' . install_url());
        exit;
    }
    $config = null;
} else {
    $config = require APP_DIR . '/config.php';
}

App\Config::load($config ?? []);

error_reporting(E_ALL);
ini_set('display_errors', App\Config::get('debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
date_default_timezone_set(App\Config::get('timezone', 'UTC'));
