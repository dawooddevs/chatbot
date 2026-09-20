<?php
declare(strict_types=1);

namespace App;

/**
 * File handling for visitor attachments (private) and site avatars (public).
 */
final class Uploads
{
    public const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;
    public const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

    /** extension => mime accepted for visitor attachments */
    public const ATTACHMENT_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'md' => 'text/plain',
        'csv' => 'text/plain',
        'json' => 'text/plain',
    ];

    public const AVATAR_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];

    /** Text types whose content is handed to the model as context. */
    private const READABLE = ['txt', 'md', 'csv', 'json'];

    /**
     * Validates and stores a visitor attachment under storage/uploads (not web
     * readable) and returns the new attachments row id.
     */
    public static function storeAttachment(array $file, int $siteId, ?int $conversationId): array
    {
        $name = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('The file could not be uploaded.');
        }
        if (!isset(self::ATTACHMENT_TYPES[$extension])) {
            throw new \RuntimeException('That file type is not supported.');
        }
        if ((int)$file['size'] > self::MAX_ATTACHMENT_BYTES) {
            throw new \RuntimeException('Files must be 5 MB or smaller.');
        }

        $tmp = (string)$file['tmp_name'];
        $mime = self::detectMime($tmp, self::ATTACHMENT_TYPES[$extension]);
        if (!in_array($mime, self::ATTACHMENT_TYPES, true)) {
            throw new \RuntimeException('That file type is not supported.');
        }

        $directory = APP_ROOT . '/storage/uploads/' . date('Y/m');
        self::ensureDirectory($directory);
        $storedName = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $target = APP_ROOT . '/storage/uploads/' . $storedName;

        if (!self::moveUploaded($tmp, $target)) {
            throw new \RuntimeException('The file could not be saved on the server.');
        }
        @chmod($target, 0640);

        $excerpt = null;
        if (in_array($extension, self::READABLE, true)) {
            $excerpt = mb_substr(Chunker::normalize((string)file_get_contents($target)), 0, 4000);
        }

        $id = Database::insert(
            'INSERT INTO attachments (site_id, conversation_id, original_name, stored_name, mime, size_bytes, excerpt, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $siteId,
                $conversationId ?: null,
                mb_substr($name, 0, 255),
                $storedName,
                $mime,
                (int)$file['size'],
                $excerpt,
            ]
        );

        return [
            'id' => $id,
            'name' => mb_substr($name, 0, 255),
            'mime' => $mime,
            'size' => (int)$file['size'],
        ];
    }

    /** Stores a site avatar in assets/uploads (web readable) and returns its URL. */
    public static function storeAvatar(array $file): string
    {
        $name = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('The image could not be uploaded.');
        }
        if (!isset(self::AVATAR_TYPES[$extension])) {
            throw new \RuntimeException('Use a PNG, JPG, GIF, WEBP or SVG image.');
        }
        if ((int)$file['size'] > self::MAX_AVATAR_BYTES) {
            throw new \RuntimeException('Images must be 2 MB or smaller.');
        }
        $mime = self::detectMime((string)$file['tmp_name'], self::AVATAR_TYPES[$extension]);
        if (!in_array($mime, self::AVATAR_TYPES, true) && $extension !== 'svg') {
            throw new \RuntimeException('That file is not a valid image.');
        }

        $directory = APP_ROOT . '/assets/uploads';
        self::ensureDirectory($directory);
        $storedName = bin2hex(random_bytes(12)) . '.' . $extension;

        if (!self::moveUploaded((string)$file['tmp_name'], $directory . '/' . $storedName)) {
            throw new \RuntimeException('The image could not be saved on the server.');
        }
        @chmod($directory . '/' . $storedName, 0644);

        return base_url('assets/uploads/' . $storedName);
    }

    public static function find(int $id, ?int $siteId = null): ?array
    {
        $sql = 'SELECT * FROM attachments WHERE id = ?';
        $params = [$id];
        if ($siteId !== null) {
            $sql .= ' AND site_id = ?';
            $params[] = $siteId;
        }
        return Database::first($sql . ' LIMIT 1', $params);
    }

    public static function path(array $attachment): string
    {
        return APP_ROOT . '/storage/uploads/' . $attachment['stored_name'];
    }

    public static function isImage(array $attachment): bool
    {
        return str_starts_with((string)$attachment['mime'], 'image/');
    }

    private static function detectMime(string $path, string $fallback): string
    {
        if (!function_exists('finfo_open')) {
            return $fallback;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return $fallback;
        }
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        // Text files are reported in many flavours; treat them all as plain text.
        if (is_string($mime) && str_starts_with($mime, 'text/')) {
            return 'text/plain';
        }
        return is_string($mime) && $mime !== '' ? $mime : $fallback;
    }

    private static function moveUploaded(string $tmp, string $target): bool
    {
        return is_uploaded_file($tmp) ? move_uploaded_file($tmp, $target) : rename($tmp, $target);
    }

    private static function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Could not create the upload folder: ' . $directory);
        }
    }
}
