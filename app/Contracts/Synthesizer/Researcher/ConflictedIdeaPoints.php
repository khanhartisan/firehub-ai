<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\CommonData\Fact;
use App\Contracts\CommonData\Point;
use App\Contracts\Serializable;
use App\Contracts\CommonData\Conflict;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\Concerns\HasIdea;

final class ConflictedIdeaPoints extends Conflict implements Serializable
{
    use HasIdea;
    use \App\Concerns\Serializable;

    /**
     * @var Point[]
     */
    protected array $points = [];

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
                    sprintf('points[%s] must be an instance of %s, %s given.', $index, Point::class, get_debug_type($point))
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

    public function getFacts(): array
    {
        return array_map(function (Point $point) {
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
            'idea' => $this->getIdea()->toArray(),
            'points' => array_map(
                static fn (Point $point): array => $point->toArray(),
                $this->getPoints()
            ),
        ];
    }

    public static function fromArray(array $data): static
    {
        if (! array_key_exists('idea', $data)) {
            throw new \Exception('idea must be set');
        }

        $rawIdea = $data['idea'];
        $idea = null;
        if ($rawIdea instanceof Idea) {
            $idea = $rawIdea;
        } elseif (is_array($rawIdea)) {
            $idea = Idea::fromArray($rawIdea);
        }

        if (! $idea instanceof Idea) {
            throw new \Exception('idea is invalid');
        }

        return (new static)
            ->setIdea($idea)
            ->hydratePoints($data)
            ->hydrateRationale($data);
    }
}