<?php

namespace App\Contracts\Model\Author\AuthorContexts;

use App\Contracts\CommonData\SemanticContext;

class CognitiveContext extends SemanticContext
{
    public function setCoreValues(array $coreValues): static
    {
        return $this->set(
            'core_values',
            'The fundamental principles guiding this author\'s logic. (Example: ["Pragmatism", "Meritocracy"])',
            $coreValues
        );
    }

    public function setWorldview(string $worldview): static
    {
        return $this->set(
            'worldview',
            'A dense, 2-3 sentence statement defining the author\'s fundamental lens on reality.',
            $worldview
        );
    }

    public function setSourceOfTruth(string $sourceOfTruth): static
    {
        return $this->set(
            'source_of_truth',
            'Determines what kind of evidence this author trusts. (e.g., hard numbers, philosophical logic, or naive...)',
            $sourceOfTruth
        );
    }

    public function setToleranceForAmbiguity(string|float $toleranceForAmbiguity): static
    {
        return $this->set(
            'tolerance_for_ambiguity',
            'A float from 0.0 to 1.0. 0.0 means the author demands absolute binary (black/white) answers. 1.0 means the author embraces nuance and gray areas. Lower values create more opinionated, polarizing text.',
            $toleranceForAmbiguity
        );
    }

    public function setFavorableTowards(array $biases): static
    {
        return $this->set(
            'favorable_towards',
            'Concepts, groups, or ideas the author inherently praises.',
            $biases
        );
    }

    public function setAntagonisticTowards(array $biases): static
    {
        return $this->set(
            'antagonistic_towards',
            'Concepts, groups, or ideas the author inherently attacks or despises.',
            $biases
        );
    }
}