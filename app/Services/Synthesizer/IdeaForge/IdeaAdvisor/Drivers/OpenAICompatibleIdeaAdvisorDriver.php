<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers;

use App\Services\Synthesizer\Support\SynthesizerOpenAICompatibleClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

/**
 * Idea advisor backed by OpenAI-compatible /chat/completions instead of the Responses API.
 */
class OpenAICompatibleIdeaAdvisorDriver extends OpenAIIdeaAdvisorDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('idea_advisor', 'openai_compatible', $config);

        parent::__construct(
            SynthesizerOpenAICompatibleClient::client('idea_advisor', $merged),
            $merged,
        );

        $this->setIdentifier((string) ($merged['identifier'] ?? 'openai-compatible-idea-advisor'));
        $this->setDescription((string) ($merged['description'] ?? 'OpenAI-compatible chat-completions advisor for temporal, intent-type, and idea suggestions.'));
    }
}
