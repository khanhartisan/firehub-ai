<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;

class OpenAIIdeaExpansionAdvisorDriver extends OpenAIIdeaAdvisorDriver
{
    public function __construct(OpenAIClient $openAIClient, array $config = [])
    {
        parent::__construct($openAIClient, $config);

        $this->setIdentifier((string) ($this->config['identifier'] ?? 'openai-idea-expansion-advisor'));
    }

    protected function buildExpansionPrompt(): string
    {
        return <<<PROMPT
Based on the provided context, your task is to brainstorm new article ideas that broaden the current scope, attract peripheral audiences, or lead existing readers into deeper/wider territories. 

While other agents focus on staying within the current topic's boundaries, your mission is to identify the "next logical step" or "adjacent horizons" that the current content touches upon but does not yet explore.
PROMPT;
    }

    protected function buildSuggestTemporalPrompt(string $clientId, SemanticContext $context): string
    {
        return $this->buildExpansionPrompt()."\n".parent::buildSuggestTemporalPrompt($clientId, $context);
    }

    protected function buildBrainstormPrompt(array $temporals, array $intentTypes, SemanticContext $context, int $limit): string
    {
        return $this->buildExpansionPrompt()."\n".parent::buildBrainstormPrompt($temporals, $intentTypes, $context, $limit);
    }
}