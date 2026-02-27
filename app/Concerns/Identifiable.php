<?php

namespace App\Concerns;

trait Identifiable
{
    protected ?string $identifier;

    public function getIdentifier(): ?string
    {
        return $this->identifier ?? null;
    }

    public function setIdentifier(?string $identifier): static
    {
        $this->identifier = $identifier;
        return $this;
    }
}