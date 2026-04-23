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

    public function hydrateConflicts(array $data): static
    {
        if (isset($data['conflicts']) && is_array($data['conflicts'])) {
            $this->setConflicts(array_values(array_map(
                static fn (Conflict|array $conflict): Conflict => $conflict instanceof Conflict ? $conflict : Conflict::fromArray($conflict),
                $data['conflicts']
            )));
        }

        return $this;
    }
}