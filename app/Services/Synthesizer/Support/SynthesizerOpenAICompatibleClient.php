<?php

namespace App\Services\Synthesizer\Support;

use App\Contracts\OpenAI\OpenAIClient;
use App\Services\OpenAI\Drivers\OpenAICompatibleDriver;
use App\Services\OpenAI\OpenAICompatibleChatCompletionsClient;

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
            'default_model' => $merged['model'] ?? $global['default_model'] ?? 'gpt-4o-mini',
            'model' => $merged['model'] ?? $global['default_model'] ?? 'gpt-4o-mini',
            'timeout' => (int) ($merged['timeout'] ?? $global['timeout'] ?? 120),
            'beta_header' => $merged['beta_header'] ?? $global['beta_header'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $configOverrides
     */
    public static function responsesClient(string $subservice, array $configOverrides = []): OpenAIClient
    {
        return new OpenAICompatibleDriver(self::connectionConfig($subservice, $configOverrides));
    }

    /**
     * @param  array<string, mixed>  $configOverrides
     */
    public static function chatCompletionsClient(string $subservice, array $configOverrides = []): OpenAICompatibleChatCompletionsClient
    {
        return new OpenAICompatibleChatCompletionsClient(self::connectionConfig($subservice, $configOverrides));
    }
}
