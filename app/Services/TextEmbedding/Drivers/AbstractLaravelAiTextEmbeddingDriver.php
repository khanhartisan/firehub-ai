<?php

namespace App\Services\TextEmbedding\Drivers;

use App\Contracts\TextEmbedding\EmbeddingOptions;
use App\Contracts\TextEmbedding\TextEmbedding;
use App\Contracts\VectorDB\Vector;
use Laravel\Ai\Embeddings;

/**
 * Base implementation for text embedding drivers that wrap the Laravel AI SDK.
 *
 * Subclasses are configured per provider (openai, azure, jina, etc.) via config;
 * the behaviour is the same, only provider name, model, and dimension differ.
 */
abstract class AbstractLaravelAiTextEmbeddingDriver implements TextEmbedding
{
    public function __construct(
        protected array $config = [],
    ) {}

    public function embed(string $text, ?EmbeddingOptions $options = null): Vector
    {
        $vectors = $this->embedMany([$text], $options);

        return $vectors[0];
    }

    public function embedMany(array $texts, ?EmbeddingOptions $options = null): array
    {
        if ($texts === []) {
            return [];
        }

        $pending = Embeddings::for($texts);

        $provider = $this->config['provider'] ?? config('ai.default_for_embeddings');
        $model = $options?->getModel() ?? $this->config['model'];
        $dimension = $options?->getDimension() ?? $this->config['dimension'] ?? 1536;

        $pending->dimensions($dimension);

        if (config('ai.caching.embeddings.cache', false)) {
            $pending->cache();
        }

        $response = $pending->generate($provider, $model);

        return $this->responseToVectors($response);
    }

    /**
     * Convert Laravel AI EmbeddingsResponse to contract Vector instances.
     *
     * @param \Laravel\Ai\Responses\EmbeddingsResponse $response
     * @return array<int, Vector>
     */
    protected function responseToVectors(\Laravel\Ai\Responses\EmbeddingsResponse $response): array
    {
        $out = [];
        foreach ($response->embeddings as $embedding) {
            $out[] = new Vector(
                array_map(fn (mixed $v): float => (float) $v, $embedding)
            );
        }

        return $out;
    }
}
