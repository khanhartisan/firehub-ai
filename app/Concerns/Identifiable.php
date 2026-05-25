<?php

namespace App\Concerns;

/**
 * Can define protected property int $maxIdentifierLength
 */
trait Identifiable
{
    protected ?string $identifier;

    public function getIdentifier(): ?string
    {
        return $this->identifier ?? null;
    }

    public function setIdentifier(?string $identifier): static
    {
        if (is_null($identifier)) {
            $this->identifier = null;
            return $this;
        }

        $this->identifier = substr($identifier, 0, $this->maxIdentifierLength ?? 40);
        return $this;
    }
}