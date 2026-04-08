<?php

namespace App\Models;

use App\Contracts\Model\Embeddable;

abstract class EmbeddableModel extends Model implements Embeddable
{
    use \App\Models\Concerns\Embeddable;

    public function getVectorIndex(): string
    {
        return $this->getTable();
    }
}