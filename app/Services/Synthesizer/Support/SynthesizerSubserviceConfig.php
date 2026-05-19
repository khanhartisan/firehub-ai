<?php

namespace App\Services\Synthesizer\Support;

final class SynthesizerSubserviceConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function settings(string $subservice, ?string $driver = null): array
    {
        $driver ??= (string) config("synthesizer.{$subservice}.default", 'openai');
        $base = config("synthesizer.{$subservice}.drivers.{$driver}", []);

        return is_array($base) ? $base : [];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function driver(string $subservice, ?string $driver = null, array $overrides = []): array
    {
        return array_merge(self::settings($subservice, $driver), $overrides);
    }
}
