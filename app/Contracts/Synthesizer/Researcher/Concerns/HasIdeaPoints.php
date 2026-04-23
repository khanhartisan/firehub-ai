<?php

namespace App\Contracts\Synthesizer\Researcher\Concerns;

use App\Contracts\Synthesizer\Researcher\IdeaPoint;

trait HasIdeaPoints
{
    /** @var IdeaPoint[] */
    protected array $ideaPoints = [];

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

    public function hydrateIdeaPoints(array $data): static
    {
        if (isset($data['idea_points']) && is_array($data['idea_points'])) {
            $hydratedIdeaPoints = [];
            foreach ($data['idea_points'] as $row) {
                if ($row instanceof IdeaPoint) {
                    $hydratedIdeaPoints[] = $row;

                    continue;
                }

                if (is_array($row)) {
                    if (! array_key_exists('idea', $row)) {
                        $row['idea'] = $this->getIdea();
                    }
                    $hydratedIdeaPoints[] = IdeaPoint::fromArray($row);
                }
            }

            $this->setIdeaPoints($hydratedIdeaPoints);
        }

        return $this;
    }
}