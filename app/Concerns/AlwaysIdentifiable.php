<?php

namespace App\Concerns;

use App\Utils\Str;

trait AlwaysIdentifiable
{
    protected ?string $identifier;

    public function getIdentifier(): ?string
    {
        return $this->identifier ??= Str::uuid()->toString();
    }

    public function setIdentifier(?string $identifier): static
    {
        if (!$identifier) {
            throw new \InvalidArgumentException('Identifier cannot be empty');
        }

        $this->identifier = $identifier;
        return $this;
    }
}