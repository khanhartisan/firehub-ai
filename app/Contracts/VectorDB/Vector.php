<?php

namespace App\Contracts\VectorDB;

/**
 * Immutable value object representing an embedding vector (list of floats).
 *
 * Replaces primitive array for type safety and a consistent data standard
 * across upsert, search, and record payloads.
 */
final readonly class Vector
{
    /** @param  array<float>  $values  Embedding dimensions */
    public function __construct(
        public array $values,
    ) {}

    /** Number of dimensions (embedding size). */
    public function dimension(): int
    {
        return count($this->values);
    }

    /**
     * Raw float array for backend implementations.
     *
     * @return array<float>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * Create from array (raw list of floats or ['values' => [...]]).
     *
     * @param  array<float>|array{values: array<float>}  $data
     */
    public static function fromArray(array $data): self
    {
        $values = isset($data['values']) && is_array($data['values'])
            ? $data['values']
            : $data;

        return new self(array_map(fn (mixed $v): float => (float) $v, $values));
    }
}
