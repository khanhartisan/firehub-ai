<?php

namespace App\Contracts\Model\Author\AuthorContexts;

use App\Contracts\CommonData\SemanticContext;

class ConstraintContext extends SemanticContext
{
    public function setEngagementStrategy(string $strategy): static
    {
        return $this->set(
            'engagement_strategy',
            'How the author psychologically interacts with the reader. (Example: Provocative, Didactic, Detached...)',
            $strategy
        );
    }

    public function setMaxHedgingRatio(string|float $maxHedgingRatio): static
    {
        return $this->set(
            'max_hedging_ratio',
            'Ranging from 0.0 to 1.0. Maximum allowed frequency of weak hedging words ("maybe", "perhaps", "could be"). A value near 0.0 enforces extreme assertiveness and confidence',
            $maxHedgingRatio
        );
    }

    public function setFormattingQuirks(array $quirks): static
    {
        return $this->set(
            'formatting_quirks',
            'Specific typographical habits. e.g., ["Heavy use of italics for emphasis", "Refuses to use bullet points",...]',
            $quirks
        );
    }
}