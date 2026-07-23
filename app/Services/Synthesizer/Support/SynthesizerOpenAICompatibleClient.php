<?php

namespace App\Services\Synthesizer\Support;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\OpenAI\Drivers\ChatCompletionsDriver;

/**
 * Builds HTTP clients for synthesizer openai_compatible drivers.
 */
final class SynthesizerOpenAICompatibleClient
{
    /**
     * @param  array<string, mixed>  $configOverrides
     * @return array<string, mixed>
     */
    public static function connectionConfig(string $subservice, array $configOverrides = []): array
    {
        $merged = SynthesizerSubserviceConfig::driver($subservice, 'openai_compatible', $configOverrides);
        $global = config('openai.drivers.openai_compatible', []);

        return [
            'api_key' => $merged['api_key'] ?? $global['api_key'] ?? '',
            'base_url' => $merged['base_url'] ?? $global['base_url'] ?? 'https://api.openai.com/v1/',
            'default_model' => $merged['model'] ?? $global['default_model'] ?? 'gpt-5.4-mini',
            'model' => $merged['model'] ?? $global['default_model'] ?? 'gpt-5.4-mini',
            'timeout' => (int) ($merged['timeout'] ?? $global['timeout'] ?? 120),
            'beta_header' => $merged['beta_header'] ?? $global['beta_header'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $configOverrides
     */
    public static function client(string $subservice, array $configOverrides = []): OpenAIClient
    {
        return new ChatCompletionsDriver(self::connectionConfig($subservice, $configOverrides));
    }
}
