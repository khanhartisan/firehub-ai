<?php

namespace App\Contracts\TextEmbedding;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

/**
 * Optional parameters for embedding requests (model, dimension).
 *
 * Passed to TextEmbedding::embed() or embedMany() when needed; most calls use
 * provider defaults. Dimension is supported by some models (e.g. OpenAI
 * text-embedding-3-*); others ignore it.
 */
final class EmbeddingOptions implements Serializable
{
    use SerializableTrait;

    protected ?string $model = null;

    /** Target dimension (e.g. for OpenAI dimension reduction); null = provider default. */
    protected ?int $dimension = null;

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getDimension(): ?int
    {
        return $this->dimension;
    }

    public function setDimension(?int $dimension): static
    {
        $this->dimension = $dimension;

        return $this;
    }

    /**
     * @return array{model: string|null, dimension: int|null}
     */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'dimension' => $this->dimension,
        ];
    }

    /**
     * @param  array{model?: string|null, dimension?: int|null}  $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $options = new static();

        if (array_key_exists('model', $data)) {
            $options->setModel($data['model']);
        }

        if (array_key_exists('dimension', $data)) {
            $options->setDimension($data['dimension']);
        }

        return $options;
    }
}
