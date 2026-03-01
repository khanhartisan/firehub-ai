<?php

namespace App\Contracts\TextEmbedding;

use App\Contracts\VectorDB\Vector;

/**
 * Abstracts text-to-embedding generation for semantic search and vector indexing.
 *
 * Implementations may use OpenAI, Azure OpenAI, Voyage, Jina, or other embedding
 * APIs. Used to produce vectors for VectorDB upsert and for query embedding in
 * VectorDB::search(). Configure via config/ai.php (default_for_embeddings).
 */
interface TextEmbedding
{
    /**
     * Embed a single text string into a vector.
     *
     * @param  string  $text  Input text (may be truncated by the provider if over limit)
     * @param  EmbeddingOptions|null  $options  Optional model, dimension, etc.
     * @return Vector  Embedding vector (dimension is provider-dependent unless set in options)
     */
    public function embed(string $text, ?EmbeddingOptions $options = null): Vector;

    /**
     * Embed multiple texts in one request (batch).
     *
     * Order of the returned vectors MUST match the order of the input texts.
     * Implementations may chunk or rate-limit internally.
     *
     * @param  array<int, string>  $texts  Input texts
     * @param  EmbeddingOptions|null  $options  Optional model, dimension, etc.
     * @return array<int, Vector>  One vector per input text, same order
     */
    public function embedMany(array $texts, ?EmbeddingOptions $options = null): array;
}
