<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\CommonData\Concerns\HasRationale;
use App\Contracts\CommonData\Concerns\HasRelevance;
use App\Contracts\CommonData\Point;

class RelevantPoint extends Point
{
    use HasRationale;
    use HasRelevance;

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'relevance' => $this->getRelevance(),
            'rationale' => $this->getRationale(),
        ]);
    }

    public static function fromArray(array $data): static
    {
        return parent::fromArray($data)
            ->hydrateRelevance($data)
            ->hydrateRationale($data);
    }
}