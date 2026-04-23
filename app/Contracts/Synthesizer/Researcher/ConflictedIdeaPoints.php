<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\CommonData\Fact;
use App\Contracts\Serializable;
use App\Contracts\CommonData\Conflict;
use App\Contracts\Synthesizer\Researcher\Concerns\HasIdea;
use App\Contracts\Synthesizer\Researcher\Concerns\HasIdeaPoints;

final class ConflictedIdeaPoints extends Conflict implements Serializable
{
    use HasIdea;
    use HasIdeaPoints;
    use \App\Concerns\Serializable;

    public function getFacts(): array
    {
        // TODO: Implement this (map from the idea points)
        return [];
    }

    public function addFact(Fact $fact): static
    {
        throw new \Exception('Prohibited');
    }

    public function setFacts(array $facts): static
    {
        throw new \Exception('Prohibited');
    }

    public function hydrateFacts(array $data): static
    {
        throw new \Exception('Prohibited');
    }
}