<?php
declare(strict_types=1);

namespace App;

use PDO;

/**
 * Creates / upgrades the database tables. Safe to run repeatedly.
 */
final class Schema
{
    /** Bump whenever statements() changes, so deployed updates migrate themselves. */
    public const VERSION = 1;

    /**
     * Runs the migrations once per schema version. Called on admin requests so a
     * code deployment never needs the installer to be re-run.
     */
    public static function ensureCurrent(): void
    {
        try {
            if ((int)Settings::get('schema_version', '0') === self::VERSION) {
                return;
            }
            self::migrate();
            Settings::put('schema_version', (string)self::VERSION);
        } catch (\Throwable $e) {
            error_log('[chatbot] schema upgrade failed: ' . $e->getMessage());
        }
    }

    public static function migrate(?PDO $pdo = null): void
    {
        $pdo = $pdo ?? Database::pdo();
        foreach (self::statements() as $sql) {
            $pdo->exec($sql);
        }
    }

    /** @return string[] */
    private static function statements(): array
    {
        $engine = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return [
            "CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(64) NOT NULL,
                email VARCHAR(191) NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'admin',
                must_change_password TINYINT(1) NOT NULL DEFAULT 0,
                last_login_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_users_username (username)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS sites (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                name VARCHAR(150) NOT NULL,
                site_key VARCHAR(40) NOT NULL,
                allowed_domains TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                design LONGTEXT NULL,
                ai LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sites_key (site_key),
                KEY idx_sites_user (user_id)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS documents (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                source_type VARCHAR(20) NOT NULL DEFAULT 'text',
                source_url VARCHAR(500) NULL,
                content LONGTEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                error TEXT NULL,
                chunk_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_documents_site (site_id)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS chunks (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                document_id INT UNSIGNED NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                position INT UNSIGNED NOT NULL DEFAULT 0,
                content MEDIUMTEXT NOT NULL,
                embedding LONGBLOB NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_chunks_site (site_id),
                KEY idx_chunks_document (document_id)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS conversations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                site_id INT UNSIGNED NOT NULL,
                visitor_id VARCHAR(64) NOT NULL,
                page_url VARCHAR(500) NULL,
                referrer VARCHAR(500) NULL,
                user_agent VARCHAR(255) NULL,
                ip_hash CHAR(64) NULL,
                message_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                last_activity_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_conversations_site (site_id, last_activity_at),
                KEY idx_conversations_visitor (visitor_id)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS messages (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id INT UNSIGNED NOT NULL,
                site_id INT UNSIGNED NOT NULL,
                role VARCHAR(16) NOT NULL,
                content MEDIUMTEXT NOT NULL,
                prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
                completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_messages_conversation (conversation_id, id),
                KEY idx_messages_site (site_id, created_at)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS settings (
                name VARCHAR(64) NOT NULL,
                value LONGTEXT NULL,
                PRIMARY KEY (name)
            ) $engine",

            "CREATE TABLE IF NOT EXISTS rate_limits (
                bucket VARCHAR(191) NOT NULL,
                window_start INT UNSIGNED NOT NULL,
                hits INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (bucket)
            ) $engine",
        ];
    }
}
