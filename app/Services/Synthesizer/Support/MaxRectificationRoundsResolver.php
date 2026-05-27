<?php

namespace App\Services\Synthesizer\Support;

/**
 * Resolves max criticize → rectify loops for the RECTIFICATION stage.
 *
 * Source of truth:
 * - per-critic override: synthesizer.drivers.{driver}.critics[].max_rectification_rounds (highest wins, null ignored)
 * - fallback: synthesizer.max_rectification_rounds
 */
final class MaxRectificationRoundsResolver
{
    public static function resolve(?string $driver = null): int
    {
        $driverName = is_string($driver) && $driver !== ''
            ? $driver
            : (string) config('synthesizer.default', 'basic');

        $driverConfig = config("synthesizer.drivers.{$driverName}");
        $criticsConfig = is_array($driverConfig) ? ($driverConfig['critics'] ?? []) : [];

        $explicit = [];

        if (is_array($criticsConfig) && array_is_list($criticsConfig)) {
            foreach ($criticsConfig as $entry) {
                if (! is_array($entry) || ! array_key_exists('max_rectification_rounds', $entry)) {
                    continue;
                }

                $value = $entry['max_rectification_rounds'];
                if ($value === null) {
                    continue;
                }

                $explicit[] = max(1, (int) $value);
            }
        }

        if ($explicit !== []) {
            return max($explicit);
        }

        return max(1, (int) config('synthesizer.max_rectification_rounds', 2));
    }
}

