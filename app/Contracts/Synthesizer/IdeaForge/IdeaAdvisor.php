<?php

namespace App\Contracts\Synthesizer\IdeaForge;

interface IdeaAdvisor
{
    /**
     * Suggest the temporal for the next idea
     *
     * @param string $clientId
     * @param string $context
     * @return TemporalSuggestion[]
     */
    public function suggestTemporal(string $clientId, string $context): array;

    /**
     * Suggest the intent types for the next idea
     *
     * @param string $clientId
     * @param string $context
     * @return IntentTypeSuggestion[]
     */
    public function suggestIntentTypes(string $clientId, string $context): array;

    /**
     * Base on the suggestions and context, give the ideas
     *
     * @param array $temporalSuggestions
     * @param array $intentTypeSuggestions
     * @param string $context
     * @param int $limit
     * @return Idea[]
     */
    public function brainstorm(array $temporalSuggestions,
                               array $intentTypeSuggestions,
                               string $context,
                               int $limit = 5): array;
}