<?php

namespace App\Contracts\Synthesizer\Author;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\Author\AuthorContexts\CognitiveContext;
use App\Contracts\Synthesizer\Author\AuthorContexts\ConstraintContext;
use App\Contracts\Synthesizer\Author\AuthorContexts\DemographicContext;
use App\Contracts\Synthesizer\Author\AuthorContexts\ExperientialContext;
use App\Contracts\Synthesizer\Author\AuthorContexts\LinguisticContext;

class AuthorContext extends SemanticContext
{
    public function setCognitiveContext(?CognitiveContext $context): static
    {
        return $this->set(
            'cognitive_context',
            'Defines the core belief system and logical processing of the author. This prevents the content from falling into the "neutrality trap".',
            $context
        );
    }

    public function setConstraintContext(?ConstraintContext $context): static
    {
        return $this->set(
            'constraint_context',
            'Real-time processing constraints for the author agent.',
            $context
        );
    }

    public function setDemographicContext(?DemographicContext $context): static
    {
        return $this->set(
            'demographic_context',
            'The physical reality of the author',
            $context
        );
    }

    public function setExperientialContext(?ExperientialContext $context): static
    {
        return $this->set(
            'experiential_context',
            'Simulates a localized database of memories and cultural anchors. Provides the author with a unique "bag of analogies" so they dont use generic ones.',
            $context
        );
    }

    public function setLinguisticContext(?LinguisticContext $context): static
    {
        return $this->set(
            'linguistic_context',
            'Defines the exact mechanical constraints of how the author outputs text. This overrides the LLM\'s default highly-polished, formulaic writing style.',
            $context
        );
    }
}