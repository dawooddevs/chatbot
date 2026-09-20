<?php
declare(strict_types=1);

namespace App;

final class Site
{
    public const DESIGN_DEFAULTS = [
        'title' => 'Chat with us',
        'subtitle' => 'Typically replies in a few seconds',
        'welcome_message' => "Hi! 👋 Ask me anything about our products and services.",
        'placeholder' => 'Type your message…',
        'primary_color' => '#4f46e5',
        'text_on_primary' => '#ffffff',
        'bubble_color' => '#4f46e5',
        'background' => '#ffffff',
        'agent_bubble' => '#f1f5f9',
        'user_bubble' => '#4f46e5',
        'font' => 'system',
        'position' => 'right',
        'offset_x' => 20,
        'offset_y' => 20,
        'radius' => 16,
        'launcher_label' => 'Chat',
        'launcher_icon' => 'chat',
        'avatar_url' => '',
        'show_status_dot' => 1,
        'show_attachments' => 1,
        'show_voice' => 1,
        'show_reset' => 1,
        'show_branding' => 1,
        'auto_open' => 0,
        'auto_open_delay' => 5,
        'suggestions' => ["What are your opening hours?", "How much does it cost?", "How do I contact support?"],
        'theme' => 'light',
    ];

    public const AI_DEFAULTS = [
        'model' => 'gpt-4o-mini',
        'embedding_model' => 'text-embedding-3-small',
        'temperature' => 0.3,
        'max_tokens' => 600,
        'history_turns' => 6,
        'top_k' => 5,
        'min_score' => 0.15,
        'persona' => 'You are a friendly, concise support assistant.',
        'fallback' => "I don't have that in my notes yet — please contact our team and they'll help you out.",
        'strict_knowledge' => 1,
        'rate_limit_per_hour' => 60,
    ];

    public static function newKey(): string
    {
        do {
            $key = 'cb_' . bin2hex(random_bytes(12));
        } while (Database::value('SELECT id FROM sites WHERE site_key = ?', [$key]) !== null);

        return $key;
    }

    public static function find(int $id, ?int $userId = null): ?array
    {
        $sql = 'SELECT * FROM sites WHERE id = ?';
        $params = [$id];
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $site = Database::first($sql . ' LIMIT 1', $params);
        return $site ? self::hydrate($site) : null;
    }

    public static function findByKey(string $key): ?array
    {
        $site = Database::first('SELECT * FROM sites WHERE site_key = ? LIMIT 1', [$key]);
        return $site ? self::hydrate($site) : null;
    }

    /** @return array<int, array> */
    public static function forUser(int $userId): array
    {
        $rows = Database::all('SELECT * FROM sites WHERE user_id = ? ORDER BY created_at DESC', [$userId]);
        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function hydrate(array $site): array
    {
        $site['design'] = self::mergeDefaults(self::decode($site['design'] ?? null), self::DESIGN_DEFAULTS);
        $site['ai'] = self::mergeDefaults(self::decode($site['ai'] ?? null), self::AI_DEFAULTS);
        $site['domains'] = self::domains((string)($site['allowed_domains'] ?? ''));
        return $site;
    }

    private static function decode(?string $json): array
    {
        $data = $json ? json_decode($json, true) : [];
        return is_array($data) ? $data : [];
    }

    private static function mergeDefaults(array $values, array $defaults): array
    {
        $merged = $defaults;
        foreach ($values as $key => $value) {
            if (array_key_exists($key, $defaults)) {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }

    /** @return string[] */
    public static function domains(string $raw): array
    {
        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $domain = self::normalizeDomain($part);
            if ($domain !== '') {
                $out[$domain] = $domain;
            }
        }
        return array_values($out);
    }

    public static function normalizeDomain(string $value): string
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            return '';
        }
        if (str_contains($value, '://')) {
            $value = (string)parse_url($value, PHP_URL_HOST);
        }
        $value = preg_replace('#[/:].*$#', '', $value) ?? $value;
        return ltrim($value, '.');
    }

    /**
     * Wildcards are supported as *.example.com; an empty allow-list means
     * "any origin", which the UI warns about.
     */
    public static function allowsOrigin(array $site, string $origin): bool
    {
        $host = self::normalizeDomain($origin);

        // The panel's own host is always allowed, so the built-in preview page
        // works without adding it to every site's list.
        if ($host !== '' && $host === self::platformHost()) {
            return true;
        }

        $domains = $site['domains'] ?? [];
        if ($domains === []) {
            return true;
        }
        if ($host === '') {
            return false;
        }
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
            if (str_starts_with($domain, '*.') && str_ends_with($host, substr($domain, 1))) {
                return true;
            }
        }
        return false;
    }

    public static function platformHost(): string
    {
        return self::normalizeDomain((string)Config::get('base_url', ''));
    }

    public static function embedCode(array $site): string
    {
        return '<script src="' . base_url('embed.php') . '?k=' . $site['site_key'] . '" async></script>';
    }
}
