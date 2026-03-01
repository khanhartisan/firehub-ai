<?php

namespace App\Models\Concerns;

use App\Contracts\VectorDB\Vector;
use App\Contracts\VectorDB\VectorRecord;
use App\Models\Model;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Enables models with a "vector" column and "is_embedded" column to implement Embeddable.
 *
 * The model must have:
 * - vector (nullable): the embedding vector (stored as pgvector; may be string or array when read)
 * - is_embedded (boolean): whether the row has been embedded
 *
 * Add to $casts: 'is_embedded' => 'boolean'
 * Optionally add 'vector' and 'is_embedded' to $fillable if you set them via mass assignment.
 */
trait Embeddable
{
    protected static function bootEmbeddable(): void
    {
        // Sync the is_embeddable field value with the isEmbeddable implementation
        static::saving(function (Model $model) {
            $model->is_embeddable = $model->isEmbeddable();
        });
    }

    public function isEmbedded(): bool
    {
        return (bool) $this->getAttribute('is_embedded');
    }

    /**
     * Save the provided vector on the model and set is_embedded to true.
     * Persists to the database.
     */
    public function setEmbedding(Vector $vector): bool
    {
        $this->setAttribute('vector', $vector->toArray());
        $this->setAttribute('is_embedded', true);

        return $this->save();
    }

    public function getVector(): ?Vector
    {
        $raw = $this->getAttribute('vector');

        if ($raw === null) {
            return null;
        }

        if (is_object($raw) && method_exists($raw, 'toArray')) {
            $values = $raw->toArray();
        } elseif (is_string($raw)) {
            $values = \App\Utils\Json::decode($raw, true) ?? [];
        } else {
            $values = (array) $raw;
        }

        if ($values === []) {
            return null;
        }

        return Vector::fromArray($values);
    }

    public function toVectorRecord(): VectorRecord
    {
        $vector = $this->getVector();

        if ($vector === null) {
            throw new InvalidArgumentException('Model has no vector; cannot build VectorRecord. Ensure the model is embedded.');
        }

        return new VectorRecord(
            id: (string) $this->getKey(),
            vector: $vector,
            metadata: $this->embeddableMetadata(),
        );
    }

    /**
     * Get models that have not yet been embedded, up to the given limit.
     *
     * @return Collection<int, static>
     */
    public static function getUnembedded(int $limit): Collection
    {
        if ($limit > 1000) {
            throw new InvalidArgumentException('Should not get more than 1000 unembedded records.');
        }

        return static::query()
            ->where('is_embeddable', true)
            ->where('is_embedded', false)
            ->orderBy('updated_at')
            ->take($limit)
            ->get();
    }

    /**
     * Metadata to attach to the vector record (e.g. for filtering in VectorDB).
     * Override in the model to include source_id, type, etc.
     *
     * @return array<string, mixed>
     */
    protected function embeddableMetadata(): array
    {
        return [];
    }
}
