<?php

namespace App\Services\Synthesizer\Support;

/**
 * Builds synthesizer profile critics[] entries and driver config for score thresholds.
 */
final class CriticProfileEntry
{
    public const float DEFAULT_MIN_CONFIDENCE = 0.8;

    public const float DEFAULT_MIN_IMPORTANCE = 0.7;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{driver: string, purpose: string, order: int, min_confidence: float, min_importance: float}&array<string, mixed>
     */
    public static function entry(
        string $driver,
        string $purpose,
        int $order,
        array $overrides = [],
    ): array {
        return array_merge([
            'driver' => $driver,
            'purpose' => $purpose,
            'order' => $order,
            'min_confidence' => self::DEFAULT_MIN_CONFIDENCE,
            'min_importance' => self::DEFAULT_MIN_IMPORTANCE,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{min_confidence: float, min_importance: float}
     */
    public static function driverConfig(array $entry): array
    {
        return [
            'min_confidence' => self::normalizeThreshold(
                $entry['min_confidence'] ?? self::DEFAULT_MIN_CONFIDENCE
            ),
            'min_importance' => self::normalizeThreshold(
                $entry['min_importance'] ?? self::DEFAULT_MIN_IMPORTANCE
            ),
        ];
    }

    public static function normalizeThreshold(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return max(0.0, min(1.0, (float) $value));
    }
}
