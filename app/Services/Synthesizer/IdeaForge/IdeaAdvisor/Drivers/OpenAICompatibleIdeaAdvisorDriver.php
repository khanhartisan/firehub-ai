<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers;

use App\Services\OpenAI\OpenAICompatibleChatCompletionsClient;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

/**
 * Idea advisor that calls OpenAI-compatible /chat/completions endpoints (Ollama, vLLM, Grok, etc.)
 * instead of the Responses API used by {@see OpenAIIdeaAdvisorDriver}.
 */
class OpenAICompatibleIdeaAdvisorDriver extends OpenAIIdeaAdvisorDriver
{
    protected OpenAICompatibleChatCompletionsClient $chatClient;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('idea_advisor', 'openai_compatible', $config);

        parent::__construct(null, $merged);

        $this->chatClient = new OpenAICompatibleChatCompletionsClient([
            'api_key' => $merged['api_key'] ?? null,
            'base_url' => $merged['base_url'] ?? null,
            'model' => $merged['model'] ?? null,
            'timeout' => $merged['timeout'] ?? null,
        ]);

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
