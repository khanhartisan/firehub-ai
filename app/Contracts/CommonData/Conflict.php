<?php

namespace App\Contracts\CommonData;

use App\Contracts\CommonData\Concerns\HasFacts;
use App\Contracts\CommonData\Concerns\HasRationale;
use App\Contracts\Serializable;

final class Conflict implements Serializable
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
        $conflict = new static;

        if (isset($data['facts']) && is_array($data['facts'])) {
            foreach ($data['facts'] as $factData) {
                $conflict->addFact(Fact::fromArray($factData));
            }
        }

        if (array_key_exists('rationale', $data)) {
            $conflict->setRationale($data['rationale'] !== null ? (string) $data['rationale'] : null);
        }

        return $conflict;
    }
}