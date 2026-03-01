<?php

namespace App\Contracts\Model;

use App\Contracts\VectorDB\Vector;
use App\Contracts\VectorDB\VectorRecord;

interface Embeddable
{
    public function isEmbedded(): bool;

    public function getVector(): ?Vector;

    public function toVectorRecord(): VectorRecord;
}