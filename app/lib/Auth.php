<?php
declare(strict_types=1);

namespace App;

final class Auth
{
    private static ?array $user = null;

    public static function attempt(string $username, string $password): bool
    {
        $user = Database::first('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);
        // Always run a hash comparison so a missing user and a wrong password cost the same.
        $hash = $user['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
        if (!password_verify($password, $hash) || !$user) {
            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [
                password_hash($password, PASSWORD_DEFAULT),
                $user['id'],
            ]);
        }

        session_regenerate_id(true);
        Session::set('user_id', (int)$user['id']);
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        self::$user = $user;
        return true;
    }

    public static function logout(): void
    {
        self::$user = null;
        Session::forget('user_id');
        session_regenerate_id(true);
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = Session::get('user_id');
        if (!$id) {
            return null;
        }
        return self::$user = Database::first('SELECT * FROM users WHERE id = ? LIMIT 1', [(int)$id]);
    }

    public static function id(): int
    {
        return (int)(self::user()['id'] ?? 0);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            Session::set('intended', $_SERVER['REQUEST_URI'] ?? null);
            redirect(admin_url('login'));
        }
        return $user;
    }

    public static function createUser(string $username, string $password, ?string $email = null, bool $mustChange = false): int
    {
        return Database::insert(
            'INSERT INTO users (username, email, password_hash, role, must_change_password, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), 'admin', $mustChange ? 1 : 0]
        );
    }

    public static function updatePassword(int $userId, string $password): void
    {
        Database::run('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [
            password_hash($password, PASSWORD_DEFAULT),
            $userId,
        ]);
    }
}
