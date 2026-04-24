<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\CommonData\Point;
use App\Contracts\Serializable;

final class ConsolidationResult implements Serializable
{
    use \App\Concerns\Serializable;

    /**
     * @var Point[]
     */
    protected array $points = [];

    /**
     * @var ConflictedIdeaPoints[]
     */
    protected array $conflicts = [];

    /**
     * @return Point[]
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    public function addPoint(Point $point): static
    {
        $this->points[] = $point;

        return $this;
    }

    /**
     * @param  Point[]  $points
     */
    public function setPoints(array $points): static
    {
        $this->points = [];
        foreach ($points as $index => $point) {
            if (! $point instanceof Point) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'points[%s] must be an instance of %s, %s given.',
                        $index,
                        Point::class,
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
                if ($row instanceof Point) {
                    $hydratedPoints[] = $row;

                    continue;
                }

                if (is_array($row)) {
                    $hydratedPoints[] = Point::fromArray($row);
                }
            }

            $this->setPoints($hydratedPoints);
        }

        return $this;
    }

    /**
     * @return ConflictedIdeaPoints[]
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    public function addConflict(ConflictedIdeaPoints $conflict): static
    {
        $this->conflicts[] = $conflict;

        return $this;
    }

    /**
     * @param  ConflictedIdeaPoints[]  $conflicts
     */
    public function setConflicts(array $conflicts): static
    {
        $this->conflicts = [];
        foreach ($conflicts as $index => $conflict) {
            if (! $conflict instanceof ConflictedIdeaPoints) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'conflicts[%s] must be an instance of %s, %s given.',
                        $index,
                        ConflictedIdeaPoints::class,
                        get_debug_type($conflict)
                    )
                );
            }

            $this->conflicts[] = $conflict;
        }

        return $this;
    }

    public function hydrateConflicts(array $data): static
    {
        if (isset($data['conflicts']) && is_array($data['conflicts'])) {
            $hydratedConflicts = [];
            foreach ($data['conflicts'] as $row) {
                if ($row instanceof ConflictedIdeaPoints) {
                    $hydratedConflicts[] = $row;

                    continue;
                }

                if (is_array($row)) {
                    $hydratedConflicts[] = ConflictedIdeaPoints::fromArray($row);
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
                static fn (Point $point): array => $point->toArray(),
                $this->getPoints()
            ),
            'conflicts' => array_map(
                static fn (ConflictedIdeaPoints $conflict): array => $conflict->toArray(),
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