<?php
declare(strict_types=1);

namespace App;

/**
 * Float32 packing so embeddings stay compact in a LONGBLOB column,
 * plus the cosine similarity used for retrieval.
 */
final class Vector
{
    /** @param float[] $vector */
    public static function pack(array $vector): string
    {
        return pack('g*', ...array_map('floatval', $vector));
    }

    /** @return float[] */
    public static function unpack(string $blob): array
    {
        if ($blob === '') {
            return [];
        }
        $values = unpack('g*', $blob);
        return $values === false ? [] : array_values($values);
    }

    /**
     * @param float[] $a
     * @param float[] $b
     */
    public static function cosine(array $a, array $b): float
    {
        $len = min(count($a), count($b));
        if ($len === 0) {
            return 0.0;
        }
        $dot = $na = $nb = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }
}
