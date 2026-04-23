<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\CommonData\Concerns\HasConflicts;

final class ConsolidatedIdeaPoint extends IdeaPoint
{
    use HasConflicts;

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'conflicts' => array_map(
                static fn ($conflict): array => $conflict->toArray(),
                $this->getConflicts()
            ),
        ]);
    }

    /**
     * @throws \Exception
     */
    public static function fromArray(array $data): static
    {
        return parent::fromArray($data)
            ->hydrateConflicts($data);
    }
}