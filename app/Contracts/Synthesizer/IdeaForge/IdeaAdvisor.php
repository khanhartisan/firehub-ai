<?php

namespace App\Contracts\Synthesizer\IdeaForge;

use App\Contracts\Describable;
use App\Contracts\Identifiable;

interface IdeaAdvisor extends Describable, Identifiable
{
    /**
     * A relative weight is useful when we have multiple idea advisors
     *
     * @return float
     */
    public function getWeight(): float;

    /**
     * Set the weight
     *
     * @param float $weight
     * @return $this
     */
    public function setWeight(float $weight): static;

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