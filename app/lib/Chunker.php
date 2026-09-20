<?php
declare(strict_types=1);

namespace App;

final class Chunker
{
    /**
     * Splits text into overlapping chunks on paragraph/sentence boundaries.
     *
     * @return string[]
     */
    public static function split(string $text, int $size = 1200, int $overlap = 150): array
    {
        $text = self::normalize($text);
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= $size) {
            return [$text];
        }

        $pieces = preg_split('/(?<=\n\n)|(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];

        $chunks = [];
        $current = '';
        foreach ($pieces as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }
            // A single oversized sentence is hard-split so nothing is dropped.
            while (mb_strlen($piece) > $size) {
                if ($current !== '') {
                    $chunks[] = trim($current);
                    $current = '';
                }
                $chunks[] = mb_substr($piece, 0, $size);
                $piece = mb_substr($piece, $size);
            }
            if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 1 > $size) {
                $chunks[] = trim($current);
                $current = $overlap > 0 ? mb_substr($current, -$overlap) . ' ' : '';
            }
            $current .= ($current === '' ? '' : ' ') . $piece;
        }
        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return array_values(array_filter($chunks, static fn (string $c): bool => $c !== ''));
    }

    public static function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
