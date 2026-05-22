<?php

namespace App\Services\Synthesizer\Support\Concerns;

use App\Services\OpenAI\OpenAICompatibleChatCompletionsClient;
use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;

trait UsesOpenAICompatibleChatCompletions
{
    protected OpenAICompatibleChatCompletionsClient $chatClient;

    /**
     * @param  array<string, mixed>  $config
     */
    protected function bootOpenAICompatibleChatCompletions(string $subservice, array $config): void
    {
        $this->chatClient = SynthesizerOpenAICompatibleClient::chatCompletionsClient($subservice, $config);
    }
}
