<?php
/**
 * Admin front controller.
 * Routes look like index.php?r=sites and map to app/controllers/<route>.php
 */
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Schema;
use App\Session;
use App\View;

Session::start();

const ROUTES = [
    'login' => 'login',
    'logout' => 'logout',
    'dashboard' => 'dashboard',
    'sites' => 'sites',
    'site' => 'site',
    'knowledge' => 'knowledge',
    'faq' => 'faq',
    'conversations' => 'conversations',
    'conversation' => 'conversation',
    'attachment' => 'attachment',
    'settings' => 'settings',
    'profile' => 'profile',
    'preview' => 'preview',
];

$route = (string)(query('r') ?? 'dashboard');
$file = ROUTES[$route] ?? null;

if ($file === null) {
    http_response_code(404);
    $file = 'dashboard';
    $route = 'dashboard';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::check();
}

$user = in_array($route, ['login'], true) ? Auth::user() : Auth::requireLogin();

// Everything is locked behind a password change until the seeded one is replaced.
if ($user && (int)$user['must_change_password'] === 1 && !in_array($route, ['profile', 'logout'], true)) {
    Session::flash('warning', 'Please choose your own password before continuing.');
    redirect(admin_url('profile'));
}

// Picks up schema changes shipped by a deployment.
if ($user) {
    Schema::ensureCurrent();
}

View::share('currentUser', $user);
View::share('currentRoute', $route);

require APP_DIR . '/controllers/' . $file . '.php';
