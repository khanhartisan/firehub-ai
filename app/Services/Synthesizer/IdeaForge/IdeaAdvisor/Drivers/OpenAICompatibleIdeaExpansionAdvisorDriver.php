<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Services\Synthesizer\Support\SynthesizerSubserviceConfig;

class OpenAICompatibleIdeaExpansionAdvisorDriver extends OpenAICompatibleIdeaAdvisorDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $merged = SynthesizerSubserviceConfig::driver('idea_advisor', 'openai_compatible', $config);

        parent::__construct($merged);

        $this->setIdentifier((string) ($merged['identifier'] ?? 'openai-compatible-idea-expansion-advisor'));
    }

    protected function buildExpansionPrompt(): string
    {
        return <<<PROMPT
Based on the provided context, your task is to brainstorm new article ideas that broaden the current scope, attract peripheral audiences, or lead existing readers into deeper/wider territories. 

While other agents focus on staying within the current topic's boundaries or main-focus value, your mission is to identify the "next logical step" or "adjacent horizons".

You look at the client context data, check the list of niches, you ONLY focus on the niches and ideas that are not yet developed or weakly developed. Your priority is reversed, which means niches and topics that are less priority by the context, is the most priority to you. The points, the directions, the ideas that should be used the most will be the less priority to you.

You have the right and the freedom to think out-of-the-box to expand the ideas to something that just basically support the audiences, or support the primary context, but not necessary to by directly-related to the primary main-focus.

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
