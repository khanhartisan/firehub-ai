<?php

namespace App\Utils;

class Math
{
    /**
     * Calculates the Euclidean distance between two vectors.
     *
     * @param array $vectorA
     * @param array $vectorB
     * @return float|null
     */
    public static function euclidean(array $vectorA, array $vectorB): ?float {
        $dimensions = count($vectorA);

        if ($dimensions !== count($vectorB) || $dimensions === 0) {
            return null;
        }

        $sum = 0;
        for ($i = 0; $i < $dimensions; $i++) {
            $sum += pow($vectorA[$i] - $vectorB[$i], 2);
        }

        return sqrt($sum);
    }

    /**
     * Calculates Cosine Similarity (Result: -1.0 to 1.0)
     * Closer to 1.0 means highly similar.
     *
     * @param array $vectorA
     * @param array $vectorB
     * @return float|null
     */
    public static function cosine(array $vectorA, array $vectorB): ?float {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;
        $dimensions = count($vectorA);

        if ($dimensions !== count($vectorB) || $dimensions === 0) {
            return null;
        }

        for ($i = 0; $i < $dimensions; $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $normA += pow($vectorA[$i], 2);
            $normB += pow($vectorB[$i], 2);
        }

        $divisor = sqrt($normA) * sqrt($normB);

        // Prevent division by zero
        if ($divisor == 0) {
            return 0;
        }

        return $dotProduct / $divisor;
    }

    public static function vectorSimilarity(array $vectorA, array $vectorB): ?float
    {
        $distance = static::euclidean($vectorA, $vectorB);
        if (is_null($distance)) {
            return null;
        }

        return round(1 / (1 + pow($distance, 2)), 8);
    }
}