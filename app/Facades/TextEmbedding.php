<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\TextEmbedding\TextEmbedding driver(string|null $driver = null)
 * @method static \App\Contracts\VectorDB\Vector embed(string $text, \App\Contracts\TextEmbedding\EmbeddingOptions|null $options = null)
 * @method static array<int, \App\Contracts\VectorDB\Vector> embedMany(array $texts, \App\Contracts\TextEmbedding\EmbeddingOptions|null $options = null)
 *
 * @see \App\Services\TextEmbedding\TextEmbeddingManager
 */
class TextEmbedding extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'text_embedding.manager';
    }
}
