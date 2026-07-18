<?php

namespace App\Concerns;

use App\Contracts\CommonData\SemanticContext;

trait Contextable
{
    protected ?SemanticContext $context = null;

    public function getContext(): ?SemanticContext
    {
        return $this->context;
    }

    public function setContext(?SemanticContext $context): static
    {
        $this->context = $context;

        return $this;
    }
}