<?php

namespace App\Contracts\Synthesizer\IdeaForge;

use App\Contracts\CommonData\SemanticContext;
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
     * @param SemanticContext $context
     * @return TemporalSuggestion[]
     */
    public function suggestTemporal(string $clientId, SemanticContext $context): array;

    /**
     * Suggest the intent types for the next idea
     *
     * @param string $clientId
     * @param SemanticContext $context
     * @return IntentTypeSuggestion[]
     */
    public function suggestIntentTypes(string $clientId, SemanticContext $context): array;

    /**
     * Base on the suggestions and context, give the ideas
     *
     * @param array $temporalSuggestions
     * @param array $intentTypeSuggestions
     * @param SemanticContext $context
     * @param int $limit
     * @return Idea[]
     */
    public function brainstorm(array $temporalSuggestions,
                               array $intentTypeSuggestions,
                               SemanticContext $context,
                               int $limit = 5): array;
}