<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\CommonData\Concerns\HasRationale;
use App\Contracts\CommonData\Point;
use App\Contracts\Serializable;
use App\Contracts\Synthesizer\IdeaForge\Idea;

class IdeaPoint implements Serializable
{
    use HasRationale;
    use \App\Concerns\Serializable;

    protected Idea $idea;

    protected Point $point;

    protected ?float $relevance = null;

    public function __construct(Idea $idea, Point $point, ?float $relevance = null, ?string $rationale = null)
    {
        $this->idea = $idea;
        $this->point = $point;
        $this->relevance = $relevance;
        $this->rationale = $rationale;
    }

    public function getIdea(): Idea
    {
        return $this->idea;
    }

    public function setIdea(Idea $idea): static
    {
        $this->idea = $idea;

        return $this;
    }

    public function getPoint(): Point
    {
        return $this->point;
    }

    public function setPoint(Point $point): static
    {
        $this->point = $point;

        return $this;
    }

    public function getRelevance(): ?float
    {
        return $this->relevance;
    }

    public function setRelevance(?float $relevance): static
    {
        $this->relevance = round($relevance, 2);

        return $this;
    }

    public function toArray(): array
    {
        return [
            'idea' => $this->getIdea()->toArray(),
            'point' => $this->getPoint()->toArray(),
            'rationale' => $this->getRationale(),
            'relevance' => $this->getRelevance(),
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

        if (! array_key_exists('point', $data)) {
            throw new \Exception('point must be set');
        }

        $rawPoint = $data['point'];
        $point = null;
        if ($rawPoint instanceof Point) {
            $point = $rawPoint;
        } elseif (is_array($rawPoint)) {
            $point = Point::fromArray($rawPoint);
        }

        if (! $point instanceof Point) {
            throw new \Exception('point is invalid');
        }

        $ideaPoint = new static($idea, $point);

        if (array_key_exists('relevance', $data)) {
            $ideaPoint->setRelevance($data['relevance'] !== null ? (float) $data['relevance'] : null);
        }

        if (array_key_exists('rationale', $data)) {
            $ideaPoint->setRationale($data['rationale'] !== null ? (string) $data['rationale'] : null);
        }

        return $ideaPoint;
    }
}