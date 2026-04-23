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

    public function hydrateFacts(array $data): static
    {
        if (isset($data['facts']) && is_array($data['facts'])) {
            $this->setFacts(array_values(array_map(
                static fn (Fact|array $fact): Fact => $fact instanceof Fact ? $fact : Fact::fromArray($fact),
                $data['facts']
            )));
        }

        return $this;
    }
}