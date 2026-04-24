<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\Serializable;

final class ConsolidationResult implements Serializable
{
    use \App\Concerns\Serializable;

    /**
     * @var RelevantPoint[]
     */
    protected array $points = [];

    /**
     * @var ConflictedPoints[]
     */
    protected array $conflicts = [];

    /**
     * @return RelevantPoint[]
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    public function addPoint(RelevantPoint $point): static
    {
        $this->points[] = $point;

        return $this;
    }

    /**
     * @param  RelevantPoint[]  $points
     */
    public function setPoints(array $points): static
    {
        $this->points = [];
        foreach ($points as $index => $point) {
            if (! $point instanceof RelevantPoint) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'points[%s] must be an instance of %s, %s given.',
                        $index,
                        RelevantPoint::class,
                        get_debug_type($point)
                    )
                );
            }

            $this->points[] = $point;
        }

        return $this;
    }

    public function hydratePoints(array $data): static
    {
        if (isset($data['points']) && is_array($data['points'])) {
            $hydratedPoints = [];
            foreach ($data['points'] as $row) {
                if ($row instanceof RelevantPoint) {
                    $hydratedPoints[] = $row;

                    continue;
                }

                if (is_array($row)) {
                    $hydratedPoints[] = RelevantPoint::fromArray($row);
                }
            }

            $this->setPoints($hydratedPoints);
        }

        return $this;
    }

    /**
     * @return ConflictedPoints[]
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    public function addConflict(ConflictedPoints $conflict): static
    {
        $this->conflicts[] = $conflict;

        return $this;
    }

    /**
     * @param  ConflictedPoints[]  $conflicts
     */
    public function setConflicts(array $conflicts): static
    {
        $this->conflicts = [];
        foreach ($conflicts as $index => $conflict) {
            if (! $conflict instanceof ConflictedPoints) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'conflicts[%s] must be an instance of %s, %s given.',
                        $index,
                        ConflictedPoints::class,
                        get_debug_type($conflict)
                    )
                );
            }

            $this->conflicts[] = $conflict;
        }

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function hydrateConflicts(array $data): static
    {
        if (isset($data['conflicts']) && is_array($data['conflicts'])) {
            $hydratedConflicts = [];
            foreach ($data['conflicts'] as $row) {
                if ($row instanceof ConflictedPoints) {
                    $hydratedConflicts[] = $row;

                    continue;
                }

                if (is_array($row)) {
                    $hydratedConflicts[] = ConflictedPoints::fromArray($row);
                }
            }

            $this->setConflicts($hydratedConflicts);
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'points' => array_map(
                static fn (RelevantPoint $point): array => $point->toArray(),
                $this->getPoints()
            ),
            'conflicts' => array_map(
                static fn (ConflictedPoints $conflict): array => $conflict->toArray(),
                $this->getConflicts()
            ),
        ];
    }

    public static function fromArray(array $data): static
    {
        $result = new static();

        return $result
            ->hydratePoints($data)
            ->hydrateConflicts($data);
    }
}