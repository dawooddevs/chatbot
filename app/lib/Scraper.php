<?php
declare(strict_types=1);

namespace App;

final class Scraper
{
    /**
     * @return array{title:string, text:string}
     */
    public static function fetch(string $url): array
    {
        $url = trim($url);
        $parts = parse_url($url);
        self::assertPublicUrl($url);

        $response = Http::request('GET', $url, ['Accept' => 'text/html,text/plain;q=0.9'], null, 45);
        if ($response['status'] >= 400) {
            throw new HttpException('The page returned HTTP ' . $response['status'] . '.');
        }

        return [
            'title' => self::extractTitle($response['body']) ?: $url,
            'text' => self::htmlToText($response['body']),
        ];
    }

    /** Rejects anything but a public http(s) address. */
    public static function assertPublicUrl(string $url): void
    {
        $parts = parse_url(trim($url));
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            throw new HttpException('Enter a full URL starting with http:// or https://');
        }
        self::assertPublicHost((string)$parts['host']);
    }

    /** Blocks loopback / private ranges so an imported URL cannot probe the host network. */
    private static function assertPublicHost(string $host): void
    {
        // Local development only; never set this on a live server.
        if (Config::get('allow_private_urls') === true) {
            return;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            throw new HttpException('That domain could not be resolved.');
        }
        foreach ($ips as $ip) {
            $public = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($public === false) {
                throw new HttpException('That address is not publicly routable.');
            }
        }
    }

    public static function extractTitle(string $html): string
    {
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return '';
    }

    /**
     * The part of a page worth reading: <main> or <article> when the theme has
     * one, otherwise the body without its header, footer, menus and sidebars.
     */
    public static function mainContent(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|svg|iframe|template)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        foreach (['main', 'article'] as $tag) {
            if (preg_match('#<' . $tag . '\b[^>]*>(.*)</' . $tag . '>#is', $html, $m)) {
                return $m[1];
            }
        }
        if (preg_match('#<body\b[^>]*>(.*)</body>#is', $html, $m)) {
            $html = $m[1];
        }
        return preg_replace('#<(header|footer|nav|aside)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|svg|iframe)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<!--.*?-->#s', ' ', $html) ?? $html;
        $html = preg_replace('#</(p|div|li|h[1-6]|tr|section|article|br)>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return Chunker::normalize($text);
    }

    /** Extracts plain text from an uploaded file (txt, md, csv, html, json). */
    public static function fromUpload(string $path, string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $raw = (string)file_get_contents($path);

        return match ($extension) {
            'html', 'htm' => self::htmlToText($raw),
            'json' => Chunker::normalize(print_r(json_decode($raw, true) ?? $raw, true)),
            default => Chunker::normalize($raw),
        };
    }

    public const ALLOWED_UPLOAD_EXTENSIONS = ['txt', 'md', 'markdown', 'csv', 'html', 'htm', 'json'];
}
