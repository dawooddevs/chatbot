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
            $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [$user['password_hash'], $user['id']]);
        }

        session_regenerate_id(true);
        Session::set('user_id', (int)$user['id']);
        Session::set('auth_marker', self::marker($user));
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        self::$user = $user;
        return true;
    }

    public static function logout(): void
    {
        self::$user = null;
        Session::forget('user_id');
        Session::forget('auth_marker');
        session_regenerate_id(true);
    }

    /**
     * A fingerprint of the password hash kept in the session: when the
     * password is changed or reset, every other session of that user ends.
     */
    private static function marker(array $user): string
    {
        return substr(hash('sha256', (string)$user['password_hash']), 0, 24);
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
        $user = Database::first('SELECT * FROM users WHERE id = ? LIMIT 1', [(int)$id]);
        $marker = Session::get('auth_marker');
        if ($user && $marker === null) {
            // Signed in before markers existed: adopt the current one.
            Session::set('auth_marker', self::marker($user));
        } elseif (!$user || !hash_equals((string)$marker, self::marker($user))) {
            // Deleted, or the password changed since this session began.
            Session::forget('user_id');
            Session::forget('auth_marker');
            return null;
        }
        return self::$user = $user;
    }

    /** The first account created; it cannot be deleted from the panel. */
    public static function ownerId(): int
    {
        return (int)Database::value('SELECT MIN(id) FROM users');
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

    public static function updatePassword(int $userId, string $password, bool $mustChange = false): void
    {
        $currentId = self::id(); // before the hash changes, or this session would look stale
        $hash = password_hash($password, PASSWORD_DEFAULT);
        Database::run('UPDATE users SET password_hash = ?, must_change_password = ? WHERE id = ?', [
            $hash,
            $mustChange ? 1 : 0,
            $userId,
        ]);
        // Changing your own password keeps you signed in here; other sessions end.
        if ($userId === $currentId) {
            Session::set('auth_marker', self::marker(['password_hash' => $hash]));
            self::$user = null;
        }
    }
}
