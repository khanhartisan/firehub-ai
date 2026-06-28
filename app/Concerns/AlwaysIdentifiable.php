<?php

namespace App\Concerns;

use App\Utils\Str;

/**
 * Can define protected properties: int $defaultIdentifierLength and int $maxIdentifierLength
 */
trait AlwaysIdentifiable
{
    protected ?string $identifier;

    public function getIdentifier(): ?string
    {
        if (isset($this->identifier)) {
            return $this->identifier;
        }

        return $this->setIdentifier(strtolower(Str::random($this->defaultIdentifierLength ?? 10)))->getIdentifier();
    }

    public function setIdentifier(?string $identifier): static
    {
        if (!$identifier) {
            throw new \InvalidArgumentException('Identifier cannot be empty');
        }

        $this->identifier = substr($identifier, 0, $this->maxIdentifierLength ?? 40);
        return $this;
    }
}