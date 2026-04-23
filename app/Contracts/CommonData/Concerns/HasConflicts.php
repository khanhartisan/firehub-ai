<?php

namespace App\Contracts\CommonData\Concerns;

use App\Contracts\CommonData\Conflict;

trait HasConflicts
{
    /**
     * @var Conflict[]
     */
    protected array $conflicts = [];

    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    public function addConflict(Conflict $conflict): static
    {
        $this->conflicts[] = $conflict;
        return $this;
    }

    public function setConflicts(array $conflicts): static
    {
        $this->conflicts = [];
        foreach ($conflicts as $conflict) {
            $this->addConflict($conflict);
        }
        return $this;
    }
}