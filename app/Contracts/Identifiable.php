<?php

namespace App\Contracts;

interface Identifiable
{
    public function getIdentifier(): ?string;

    public function setIdentifier(?string $identifier): static;
}