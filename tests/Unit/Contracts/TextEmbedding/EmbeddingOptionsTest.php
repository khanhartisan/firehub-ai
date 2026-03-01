<?php

namespace Tests\Unit\Contracts\TextEmbedding;

use App\Contracts\TextEmbedding\EmbeddingOptions;
use Tests\TestCase;

class EmbeddingOptionsTest extends TestCase
{
    public function test_from_array_sets_model_and_dimension(): void
    {
        $options = EmbeddingOptions::fromArray([
            'model' => 'text-embedding-3-large',
            'dimension' => 3072,
        ]);

        $this->assertSame('text-embedding-3-large', $options->getModel());
        $this->assertSame(3072, $options->getDimension());
    }

    public function test_to_array_returns_model_and_dimension(): void
    {
        $options = (new EmbeddingOptions())
            ->setModel('text-embedding-3-small')
            ->setDimension(1536);

        $this->assertSame([
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ], $options->toArray());
    }

    public function test_from_array_handles_partial_data(): void
    {
        $options = EmbeddingOptions::fromArray(['dimension' => 256]);

        $this->assertNull($options->getModel());
        $this->assertSame(256, $options->getDimension());
    }

    public function test_fluent_setters_return_self(): void
    {
        $options = new EmbeddingOptions();

        $this->assertSame($options, $options->setModel('m'));
        $this->assertSame($options, $options->setDimension(128));
    }

    public function test_serialization_round_trip(): void
    {
        $data = ['model' => 'custom-model', 'dimension' => 512];
        $options = EmbeddingOptions::fromArray($data);

        $this->assertEquals($data, $options->toArray());
        $this->assertEquals($options->getModel(), EmbeddingOptions::fromArray($options->toArray())->getModel());
        $this->assertEquals($options->getDimension(), EmbeddingOptions::fromArray($options->toArray())->getDimension());
    }
}
