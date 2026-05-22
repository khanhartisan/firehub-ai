<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers;

use App\Services\Synthesizer\Support\Concerns\UsesOpenAICompatibleChatCompletions;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

/**
 * Idea advisor that calls OpenAI-compatible /chat/completions endpoints
 * instead of the Responses API used by {@see OpenAIIdeaAdvisorDriver}.
 */
class OpenAICompatibleIdeaAdvisorDriver extends OpenAIIdeaAdvisorDriver
{
    use UsesOpenAICompatibleChatCompletions;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('idea_advisor', 'openai_compatible', $config);

        parent::__construct(null, $merged);

        $this->bootOpenAICompatibleChatCompletions('idea_advisor', $merged);

        $this->setIdentifier((string) ($merged['identifier'] ?? 'openai-compatible-idea-advisor'));
        $this->setDescription((string) ($merged['description'] ?? 'OpenAI-compatible chat-completions advisor for temporal, intent-type, and idea suggestions.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestStructuredJson(string $prompt, string $schemaName, array $jsonSchema, string $failureMessage): array
    {
        return $this->chatClient->requestStructuredJson(
            $prompt,
            $schemaName,
            $jsonSchema,
            $failureMessage,
            $this->getModel(),
            $this->getTemperature(),
            (string) ($this->config['structured_output'] ?? 'json_schema'),
        );
    }
}
