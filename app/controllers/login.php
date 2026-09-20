<?php
declare(strict_types=1);

use App\Auth;
use App\RateLimiter;
use App\Session;
use App\View;

if (Auth::check()) {
    redirect(admin_url('dashboard'));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string)post('username', '');
    $password = (string)post('password', '');
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');

    if (!RateLimiter::hit('login:' . $ip, 10, 900)) {
        $error = 'Too many attempts. Wait 15 minutes and try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } elseif (Auth::attempt($username, $password)) {
        $intended = Session::get('intended');
        Session::forget('intended');
        redirect(is_string($intended) && $intended !== '' ? $intended : admin_url('dashboard'));
    } else {
        $error = 'Those details did not match an account.';
    }
}

View::display('admin.login', ['error' => $error], 'auth_layout');
