<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\Serializable;
use App\Contracts\Synthesizer\IdeaForge\Idea;

final class IdeaPoints implements Serializable
{
    use \App\Concerns\Serializable;

    protected Idea $idea;

    /** @var IdeaPoint[] */
    protected array $ideaPoints = [];

    public function __construct(Idea $idea, array $ideaPoints = [])
    {
        $this->idea = $idea;
        $this->setIdeaPoints($ideaPoints);
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

    /**
     * @return IdeaPoint[]
     */
    public function getIdeaPoints(): array
    {
        return $this->ideaPoints;
    }

    public function addIdeaPoint(IdeaPoint $ideaPoint): static
    {
        $this->ideaPoints[] = $ideaPoint;

        return $this;
    }

    /**
     * @param  IdeaPoint[]  $ideaPoints
     */
    public function setIdeaPoints(array $ideaPoints): static
    {
        $this->ideaPoints = [];
        foreach ($ideaPoints as $index => $ideaPoint) {
            if (! $ideaPoint instanceof IdeaPoint) {
                throw new \InvalidArgumentException(
                    sprintf('ideaPoints[%s] must be an instance of %s, %s given.', $index, IdeaPoint::class, get_debug_type($ideaPoint))
                );
            }

            $this->ideaPoints[] = $ideaPoint;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'idea' => $this->getIdea()->toArray(),
            'idea_points' => array_map(
                static function (IdeaPoint $ideaPoint): array {
                    $row = $ideaPoint->toArray();
                    unset($row['idea']);

                    return $row;
                },
                $this->getIdeaPoints()
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

        $ideaPoints = new static($idea);

        if (isset($data['idea_points']) && is_array($data['idea_points'])) {
            $hydratedIdeaPoints = [];
            foreach ($data['idea_points'] as $row) {
                if ($row instanceof IdeaPoint) {
                    $hydratedIdeaPoints[] = $row;

                    continue;
                }

                if (is_array($row)) {
                    if (! array_key_exists('idea', $row)) {
                        $row['idea'] = $idea;
                    }
                    $hydratedIdeaPoints[] = IdeaPoint::fromArray($row);
                }
            }

            $ideaPoints->setIdeaPoints($hydratedIdeaPoints);
        }

        return $ideaPoints;
    }
}