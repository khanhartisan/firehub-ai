<?php

namespace App\Services\Synthesizer\Support;

/**
 * Resolves max criticize → rectify loops per critic from profile entries.
 *
 * - per critic: synthesizer.drivers.{driver}.critics[].max_rectification_rounds when set (non-null)
 * - fallback per critic: synthesizer.max_rectification_rounds
 */
final class MaxRectificationRoundsResolver
{
    public static function globalDefault(): int
    {
        return max(1, (int) config('synthesizer.max_rectification_rounds', 2));
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function forEntry(array $entry, ?int $globalDefault = null): int
    {
        $globalDefault ??= self::globalDefault();

        if (! array_key_exists('max_rectification_rounds', $entry)) {
            return $globalDefault;
        }

        $value = $entry['max_rectification_rounds'];
        if ($value === null) {
            return $globalDefault;
        }

        return max(1, (int) $value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function profileEntries(?string $driver = null): array
    {
        $driverName = is_string($driver) && $driver !== ''
            ? $driver
            : (string) config('synthesizer.default', 'basic');

        $driverConfig = config("synthesizer.drivers.{$driverName}");
        $criticsConfig = is_array($driverConfig) ? ($driverConfig['critics'] ?? []) : [];

        if (! is_array($criticsConfig) || ! array_is_list($criticsConfig)) {
            return [];
        }

        return array_values(array_filter(
            $criticsConfig,
            static fn (mixed $entry): bool => is_array($entry)
        ));
    }
}
