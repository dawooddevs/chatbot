<?php
declare(strict_types=1);

use App\Auth;
use App\Database;
use App\Session;
use App\View;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'password') {
    $current = (string)post('current_password', '');
    $new = (string)post('new_password', '');
    $confirm = (string)post('confirm_password', '');

    if (!password_verify($current, (string)$user['password_hash'])) {
        $errors[] = 'Your current password is not correct.';
    }
    if (strlen($new) < 10) {
        $errors[] = 'The new password must be at least 10 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }

    if ($errors === []) {
        Auth::updatePassword((int)$user['id'], $new);
        Session::flash('success', 'Password updated.');
        redirect(admin_url('profile'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'account') {
    Database::run('UPDATE users SET email = ? WHERE id = ?', [post('email') ?: null, $user['id']]);
    Session::flash('success', 'Account details saved.');
    redirect(admin_url('profile'));
}

View::display('admin.profile', [
    'errors' => $errors,
    'user' => Auth::user(),
]);
