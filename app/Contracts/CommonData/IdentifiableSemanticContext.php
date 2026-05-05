<?php

namespace App\Contracts\CommonData;

use App\Concerns\AlwaysIdentifiable;
use App\Contracts\Identifiable;

class IdentifiableSemanticContext extends SemanticContext implements Identifiable
{
    use AlwaysIdentifiable;

    public function toArray(): array
    {
        return array_merge([
            'identifier' => $this->getIdentifier(),
        ], parent::toArray());
    }

    public function loadFromArray(array $data): static
    {
        $context = parent::loadFromArray($data);
        if (isset($data['identifier']) and is_string($data['identifier'])) {
            $context->setIdentifier($data['identifier']);
        }
        return $context;
    }
}