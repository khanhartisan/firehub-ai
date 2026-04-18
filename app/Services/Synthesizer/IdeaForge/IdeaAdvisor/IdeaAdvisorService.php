<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAdvisor;

use App\Concerns\Describable;
use App\Concerns\Identifiable;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;

abstract class IdeaAdvisorService implements IdeaAdvisor
{
    use Describable;
    use Identifiable {
        getIdentifier as private getIdentifierFromTrait;
    }

    protected float $weight = 1;

    public function getIdentifier(): ?string
    {
        $identifier = trim((string) $this->getIdentifierFromTrait());
        if ($identifier === '') {
            throw new \RuntimeException(sprintf(
                'Idea advisor "%s" must define a non-empty identifier.',
                static::class
            ));
        }

        return $identifier;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): static
    {
        $this->weight = abs($weight);
        return $this;
    }
}
