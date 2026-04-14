<?php

namespace App\Contracts\Synthesizer\IdeaForge;

interface IdeaAdvisor
{
    /**
     * Suggest the temporal for the next idea for the given client id
     *
     * @param string $clientId
     * @param string|null $context
     * @return TemporalSuggestion[]
     */
    public function suggestTemporal(string $clientId, ?string $context = null): array;

    /**
     * @param string $clientId
     * @param string|null $context
     * @return IntentTypeSuggestion[]
     */
    public function suggestIntentTypes(string $clientId, ?string $context = null): array;
}