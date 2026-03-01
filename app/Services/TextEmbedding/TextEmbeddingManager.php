<?php

namespace App\Services\TextEmbedding;

use Illuminate\Support\Manager;

class TextEmbeddingManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('text_embedding.default', 'openai');
    }

    /**
     * Create the OpenAI driver instance (wraps Laravel AI Embeddings).
     */
    protected function createOpenaiDriver(): Drivers\OpenAITextEmbeddingDriver
    {
        $config = $this->config->get('text_embedding.drivers.openai', []);

        return new Drivers\OpenAITextEmbeddingDriver($config);
    }

    /**
     * Create the Azure driver instance (wraps Laravel AI Embeddings).
     */
    protected function createAzureDriver(): Drivers\AzureTextEmbeddingDriver
    {
        $config = $this->config->get('text_embedding.drivers.azure', []);

        return new Drivers\AzureTextEmbeddingDriver($config);
    }
}
