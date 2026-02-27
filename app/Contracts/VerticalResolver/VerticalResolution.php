<?php

namespace App\Contracts\VerticalResolver;

use App\Concerns\Serializable as SerializableConcern;
use App\Contracts\Serializable;

final class VerticalResolution implements Serializable
{
    use SerializableConcern;

    /**
     * @var VerticalMatch[]
     */
    private array $matches = [];

    /**
     * Propose to create new verticals.
     * An array of new verticals that matched.
     *
     * @var Vertical[]
     */
    private array $proposals = [];

    public function getMatches(): array
    {
        return $this->matches;
    }

    public function setMatches(array $matches): static
    {
        $this->matches = $matches;
        return $this;
    }

    public function getProposals(): array
    {
        return $this->proposals;
    }

    public function setProposals(array $proposals): static
    {
        $this->proposals = $proposals;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'matches' => array_map(
                fn (VerticalMatch $match) => $match->toArray(),
                $this->getMatches()
            ),
            'proposals' => array_map(
                fn (VerticalMatch $match) => $match->toArray(),
                $this->getProposals()
            )
        ];
    }

    public static function fromArray(array $data): static
    {
        $verticalResolution = new static();
        $verticalResolution->setMatches(
            array_map(
                fn (array $verticalMatchData)
                    => VerticalMatch::fromArray($verticalMatchData),
            $data['matches'] ?? []
            )
        );
        $verticalResolution->setProposals(
            array_map(
                fn (array $proposalData)
                    => VerticalMatch::fromArray($proposalData),
                $data['proposals'] ?? []
            )
        );
        return $verticalResolution;
    }
}