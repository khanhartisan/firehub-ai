<?php

namespace App\Contracts\CommonData\Concerns;

use App\Contracts\CommonData\Fact;

trait HasFacts
{
    /**
     * @var Fact[]
     */
    protected array $facts = [];

    public function getFacts(): array
    {
        return $this->facts;
    }

    public function addFact(Fact $fact): static
    {
        $this->facts[] = $fact;
        return $this;
    }

    public function setFacts(array $facts): static
    {
        $this->facts = [];
        foreach ($facts as $fact) {
            $this->addFact($fact);
        }
        return $this;
    }
}