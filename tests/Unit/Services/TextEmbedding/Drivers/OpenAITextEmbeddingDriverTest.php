<?php

namespace Tests\Unit\Services\TextEmbedding\Drivers;

use App\Contracts\TextEmbedding\EmbeddingOptions;
use App\Contracts\VectorDB\Vector;
use App\Services\TextEmbedding\Drivers\OpenAITextEmbeddingDriver;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class OpenAITextEmbeddingDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Embeddings::fake();
        config(['ai.default_for_embeddings' => 'openai']);
    }

    public function test_embed_returns_vector(): void
    {
        $driver = new OpenAITextEmbeddingDriver([
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

        $vector = $driver->embed('Hello world');

        $this->assertInstanceOf(Vector::class, $vector);
        $this->assertSame(1536, $vector->dimension());
        $this->assertCount(1536, $vector->values);
    }

    public function test_embed_many_returns_vectors_in_same_order(): void
    {
        $driver = new OpenAITextEmbeddingDriver([
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'dimension' => 4,
        ]);

        $vectors = $driver->embedMany(['first', 'second', 'third']);

        $this->assertCount(3, $vectors);
        $this->assertContainsOnlyInstancesOf(Vector::class, $vectors);
        foreach ($vectors as $vector) {
            $this->assertSame(4, $vector->dimension());
        }
    }

    public function test_embed_many_empty_returns_empty_array(): void
    {
        $driver = new OpenAITextEmbeddingDriver([
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

        $vectors = $driver->embedMany([]);

        $this->assertSame([], $vectors);
    }

    public function test_embed_uses_options_override_for_model_and_dimension(): void
    {
        $driver = new OpenAITextEmbeddingDriver([
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

        $options = (new EmbeddingOptions())->setModel('text-embedding-3-large')->setDimension(256);
        $vector = $driver->embed('Test', $options);

        $this->assertInstanceOf(Vector::class, $vector);
        $this->assertSame(256, $vector->dimension());
    }

    public function test_embed_many_uses_options_override(): void
    {
        $driver = new OpenAITextEmbeddingDriver([
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

        $options = EmbeddingOptions::fromArray(['dimension' => 8]);
        $vectors = $driver->embedMany(['a', 'b'], $options);

        $this->assertCount(2, $vectors);
        $this->assertSame(8, $vectors[0]->dimension());
        $this->assertSame(8, $vectors[1]->dimension());
    }

    public function test_embed_uses_config_when_options_null(): void
    {
        $driver = new OpenAITextEmbeddingDriver([
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'dimension' => 768,
        ]);

        $vector = $driver->embed('No options');

        $this->assertSame(768, $vector->dimension());
    }

    public function test_vector_values_are_floats(): void
    {
        $driver = new OpenAITextEmbeddingDriver([
            'provider' => 'openai',
            'model' => 'text-embedding-3-small',
            'dimension' => 3,
        ]);

        $vector = $driver->embed('Test');

        foreach ($vector->values as $value) {
            $this->assertIsFloat($value);
        }
    }
}
