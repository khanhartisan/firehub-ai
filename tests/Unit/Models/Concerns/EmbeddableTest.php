<?php

namespace Tests\Unit\Models\Concerns;

use App\Contracts\Model\Embeddable as EmbeddableContract;
use App\Contracts\VectorDB\Vector;
use App\Contracts\VectorDB\VectorRecord;
use App\Models\Source;
use InvalidArgumentException;
use Tests\TestCase;

class EmbeddableTest extends TestCase
{
    public function test_is_embedded_returns_false_when_not_embedded(): void
    {
        $source = new Source;
        $source->setRawAttributes([
            'base_url' => fake()->url(),
            'is_embedded' => false,
        ]);

        $this->assertFalse($source->isEmbedded());
    }

    public function test_is_embedded_returns_true_when_embedded(): void
    {
        $source = new Source;
        $source->setRawAttributes([
            'base_url' => fake()->url(),
            'description' => fake()->sentence(),
            'is_embedded' => true,
        ]);
        $this->assertTrue($source->save());

        // Is embedded is false because base_url was changed
        $this->assertFalse($source->isEmbedded());

        // Update again and it'll be true
        $source->is_embedded = true;
        $this->assertTrue($source->save());
        $this->assertTrue($source->isEmbedded());
    }

    public function test_get_vector_returns_null_when_vector_not_set(): void
    {
        $source = new Source;
        $source->setRawAttributes([
            'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'base_url' => 'https://example.com',
            'vector' => null,
        ]);

        $this->assertNull($source->getVector());
    }

    public function test_get_vector_returns_null_when_vector_is_empty_array(): void
    {
        $source = new Source;
        $source->setRawAttributes([
            'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'base_url' => 'https://example.com',
            'vector' => [],
        ]);

        $this->assertNull($source->getVector());
    }

    public function test_get_vector_returns_vector_when_vector_is_array(): void
    {
        $values = [0.1, -0.2, 0.3];
        $source = new Source;
        $source->setRawAttributes([
            'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'base_url' => 'https://example.com',
            'vector' => $values,
        ]);

        $vector = $source->getVector();

        $this->assertInstanceOf(Vector::class, $vector);
        $this->assertSame(3, $vector->dimension());
        $this->assertSame($values, $vector->toArray());
    }

    public function test_get_vector_returns_vector_when_vector_is_json_string(): void
    {
        $values = [0.1, -0.2, 0.3];
        $source = new Source;
        $source->setRawAttributes([
            'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'base_url' => 'https://example.com',
            'vector' => json_encode($values),
        ]);

        $vector = $source->getVector();

        $this->assertInstanceOf(Vector::class, $vector);
        $this->assertSame(3, $vector->dimension());
        $this->assertEquals($values, $vector->toArray());
    }

    public function test_get_vector_handles_object_with_to_array(): void
    {
        $values = [0.5, 0.5];
        $source = new Source;
        $source->setRawAttributes([
            'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'base_url' => 'https://example.com',
            'vector' => new class($values) {
                public function __construct(private array $values) {}

                public function toArray(): array
                {
                    return $this->values;
                }
            },
        ]);

        $vector = $source->getVector();

        $this->assertInstanceOf(Vector::class, $vector);
        $this->assertSame(2, $vector->dimension());
        $this->assertEquals($values, $vector->toArray());
    }

    public function test_to_vector_record_returns_record_when_embedded(): void
    {
        $values = [0.1, -0.2, 0.3];
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $source = new Source;
        $source->setRawAttributes([
            'id' => $id,
            'base_url' => 'https://example.com',
            'vector' => $values,
        ]);

        $record = $source->toVectorRecord();

        $this->assertInstanceOf(VectorRecord::class, $record);
        $this->assertSame($id, $record->id);
        $this->assertInstanceOf(Vector::class, $record->vector);
        $this->assertSame(3, $record->vector->dimension());
        $this->assertSame([], $record->metadata);
    }

    public function test_to_vector_record_throws_when_not_embedded(): void
    {
        $source = new Source;
        $source->setRawAttributes([
            'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'base_url' => 'https://example.com',
            'vector' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Model has no vector');

        $source->toVectorRecord();
    }

    public function test_source_implements_embeddable_contract(): void
    {
        $this->assertInstanceOf(EmbeddableContract::class, new Source);
    }

    public function test_set_embedding_sets_vector_and_is_embedded_and_returns_true(): void
    {
        $vector = new Vector([0.1, -0.2, 0.3]);
        $source = new class extends Source {
            public function save(array $options = []): bool
            {
                $this->finishSave($options);
                return true;
            }
        };
        $source->setRawAttributes([
            'base_url' => fake()->url(),
            'description' => 'Some description',
            'is_embedded' => false,
        ]);
        $this->assertTrue($source->save());
        $this->assertFalse($source->isEmbedded());

        $result = $source->setEmbedding($vector);

        $this->assertTrue($result);
        $this->assertTrue($source->isEmbedded());
        $this->assertInstanceOf(Vector::class, $source->getVector());
        $this->assertSame(3, $source->getVector()->dimension());
        $this->assertEquals([0.1, -0.2, 0.3], $source->getVector()->toArray());
    }
}
