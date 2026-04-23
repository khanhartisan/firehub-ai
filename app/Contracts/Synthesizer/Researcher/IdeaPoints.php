<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\Serializable;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\Concerns\HasIdea;
use App\Contracts\Synthesizer\Researcher\Concerns\HasIdeaPoints;

final class IdeaPoints implements Serializable
{
    use HasIdea;
    use HasIdeaPoints;
    use \App\Concerns\Serializable;

    public function __construct(Idea $idea, array $ideaPoints = [])
    {
        $this->setIdea($idea);
        $this->setIdeaPoints($ideaPoints);
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
        $ideaPoints->hydrateIdeaPoints($data);

        return $ideaPoints;
    }
}