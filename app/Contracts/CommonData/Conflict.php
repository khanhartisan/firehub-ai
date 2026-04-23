<?php

namespace App\Contracts\CommonData;

use App\Contracts\CommonData\Concerns\HasFacts;
use App\Contracts\CommonData\Concerns\HasRationale;
use App\Contracts\Serializable;

class Conflict implements Serializable
{
    use \App\Concerns\Serializable;
    use HasRationale;
    use HasFacts;

    public function toArray(): array
    {
        return [
            'facts' => array_map(fn (Fact $fact) => $fact->toArray(), $this->getFacts()),
            'rationale' => $this->getRationale(),
        ];
    }

    public static function fromArray(array $data): static
    {
        return (new static)
            ->hydrateFacts($data)
            ->hydrateRationale($data);
    }
}