<?php
declare(strict_types=1);

use App\Auth;
use App\Database;
use App\Session;
use App\View;

$errors = [];
$ownerId = Auth::ownerId();

function valid_username(string $username): bool
{
    return (bool)preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'create') {
        $username = (string)post('username', '');
        $email = (string)post('email', '');
        $password = (string)($_POST['password'] ?? '');

        if (!valid_username($username)) {
            $errors[] = 'Usernames are 3–64 characters: letters, numbers, dot, dash or underscore.';
        } elseif (Database::value('SELECT id FROM users WHERE username = ?', [$username]) !== null) {
            $errors[] = 'That username is taken.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That email address is not valid.';
        }
        if (strlen($password) < 10) {
            $errors[] = 'Passwords need at least 10 characters.';
        }

        if ($errors === []) {
            Auth::createUser($username, $password, $email ?: null, post('must_change') === '1');
            Session::flash('success', 'User ' . $username . ' created.');
            redirect(admin_url('users'));
        }
    }

    if ($action === 'reset') {
        $id = (int)post('id');
        $password = (string)($_POST['password'] ?? '');
        $target = Database::first('SELECT id, username FROM users WHERE id = ?', [$id]);

        if (!$target) {
            Session::flash('error', 'That user no longer exists.');
        } elseif (strlen($password) < 10) {
            Session::flash('error', 'Passwords need at least 10 characters.');
        } else {
            // Someone else's reset signs them out everywhere; they choose their own at next sign-in.
            Auth::updatePassword($id, $password, $id !== Auth::id());
            Session::flash('success', 'Password reset for ' . $target['username'] . '.');
        }
        redirect(admin_url('users'));
    }

    if ($action === 'delete') {
        $id = (int)post('id');
        if ($id === Auth::id()) {
            Session::flash('error', 'You cannot delete your own account.');
        } elseif ($id === $ownerId) {
            Session::flash('error', 'The owner account cannot be deleted.');
        } else {
            $target = Database::first('SELECT username FROM users WHERE id = ?', [$id]);
            Database::run('DELETE FROM users WHERE id = ?', [$id]);
            if ($target) {
                Session::flash('success', 'User ' . $target['username'] . ' deleted.');
            }
        }
        redirect(admin_url('users'));
    }
}

View::display('admin.users', [
    'users' => Database::all('SELECT id, username, email, must_change_password, last_login_at, created_at FROM users ORDER BY id'),
    'ownerId' => $ownerId,
    'meId' => Auth::id(),
    'errors' => $errors,
]);
