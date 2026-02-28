<?php

namespace App\Contracts\VectorDB;

use App\Concerns\Serializable as SerializableConcern;
use App\Contracts\Serializable;

/**
 * A single vector record with ID, embedding, and optional metadata.
 *
 * Used for upsert operations and as the payload in search results.
 */
final readonly class VectorRecord implements Serializable
{
    use SerializableConcern;

    public function __construct(
        public string $id,
        public Vector $vector,
        public array  $metadata = [],
    ) {}

    /**
     * Create from array representation.
     *
     * @param  array{id: string, vector: array<float>|array{values: array<float>}, metadata?: array<string, mixed>}  $data
     */
    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'],
            vector: Vector::fromArray($data['vector']),
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * @return array{id: string, vector: array<float>, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'vector' => $this->vector->toArray(),
            'metadata' => $this->metadata,
        ];
    }
}
