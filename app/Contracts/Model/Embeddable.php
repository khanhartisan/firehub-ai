<?php

namespace App\Contracts\Model;

use App\Contracts\VectorDB\Vector;
use App\Contracts\VectorDB\VectorRecord;
use Illuminate\Database\Eloquent\Collection;

interface Embeddable
{
    /**
     * Whether if the model is ready for embedding
     *
     * @return bool
     */
    public function isEmbeddable(): bool;

    public function isEmbedded(): bool;

    public function getTextForEmbedding(): ?string;

    /**
     * This method saves the provided vector in to the VectorDB.
     * It will also change the isEmbedded() state.
     *
     * @param Vector $vector
     * @return bool
     */
    public function setEmbedding(Vector $vector): bool;

    public function getVector(): ?Vector;

    public function toVectorRecord(): VectorRecord;

    public static function getUnembedded(int $limit): Collection;
}