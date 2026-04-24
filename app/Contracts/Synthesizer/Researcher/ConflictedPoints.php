<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\CommonData\Fact;
use App\Contracts\Serializable;
use App\Contracts\CommonData\Conflict;

final class ConflictedPoints extends Conflict implements Serializable
{
    use \App\Concerns\Serializable;

    /**
     * @var RelevantPoint[]
     */
    protected array $points = [];

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
                    sprintf('points[%s] must be an instance of %s, %s given.', $index, RelevantPoint::class, get_debug_type($point))
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

    public function getFacts(): array
    {
        return array_map(function (RelevantPoint $point) {
            return new Fact($point->getFactClaim());
        }, $this->getPoints());
    }

    public function addFact(Fact $fact): static
    {
        throw new \BadMethodCallException('Facts are derived from idea points and cannot be mutated directly.');
    }

    public function setFacts(array $facts): static
    {
        throw new \BadMethodCallException('Facts are derived from idea points and cannot be mutated directly.');
    }

    public function hydrateFacts(array $data): static
    {
        throw new \BadMethodCallException('Facts are derived from idea points and cannot be mutated directly.');
    }

    public function toArray(): array
    {
        return [
            'rationale' => $this->getRationale(),
            'points' => array_map(
                static fn (RelevantPoint $point): array => $point->toArray(),
                $this->getPoints()
            ),
        ];
    }

    public static function fromArray(array $data): static
    {
        return (new static)
            ->hydratePoints($data)
            ->hydrateRationale($data);
    }
}