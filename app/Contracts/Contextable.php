<?php

namespace App\Contracts;

use App\Contracts\CommonData\SemanticContext;

interface Contextable
{
    public function setContext(?SemanticContext $context): static;

    public function getContext(): ?SemanticContext;
}