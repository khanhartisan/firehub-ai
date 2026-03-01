<?php

namespace Tests\Unit\Services\TextEmbedding\Drivers;

use App\Contracts\VectorDB\Vector;
use App\Services\TextEmbedding\Drivers\AzureTextEmbeddingDriver;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class AzureTextEmbeddingDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Embeddings::fake();
    }

    public function test_embed_returns_vector_using_azure_config(): void
    {
        $driver = new AzureTextEmbeddingDriver([
            'provider' => 'azure',
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

        $vector = $driver->embed('Hello world');

        $this->assertInstanceOf(Vector::class, $vector);
        $this->assertSame(1536, $vector->dimension());
    }

    public function test_embed_many_returns_vectors_in_order(): void
    {
        $driver = new AzureTextEmbeddingDriver([
            'provider' => 'azure',
            'model' => 'text-embedding-3-small',
            'dimension' => 4,
        ]);

        $vectors = $driver->embedMany(['a', 'b']);

        $this->assertCount(2, $vectors);
        $this->assertContainsOnlyInstancesOf(Vector::class, $vectors);
    }
}
