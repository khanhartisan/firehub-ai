<?php

namespace Tests\Feature\Jobs;

use App\Contracts\VectorDB\Vector;
use App\Facades\TextEmbedding;
use App\Jobs\EmbeddingJob;
use App\Models\Source;
use App\Models\Vertical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Mockery;
use Tests\TestCase;

class EmbeddingJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_skips_when_model_is_not_embeddable(): void
    {
        $source = Source::factory()->create(['description' => null]);

        TextEmbedding::shouldReceive('embed')->never();

        (new EmbeddingJob($source))->handle();

        $source->refresh();
        $this->assertFalse($source->isEmbeddable());
        $this->assertFalse($source->isEmbedded());
    }

    public function test_skips_when_model_is_already_embedded(): void
    {
        $source = Source::factory()->create(['description' => 'Already done.']);
        $source->setEmbedding(new Vector(Embeddings::fakeEmbedding(1536)));
        $source->refresh();

        TextEmbedding::shouldReceive('embed')->never();

        (new EmbeddingJob($source))->handle();

        $source->refresh();
        $this->assertTrue($source->isEmbedded());
    }

    public function test_skips_when_get_text_for_embedding_is_null(): void
    {
        $vertical = Vertical::create([
            'name' => '',
            'description' => null,
        ]);

        TextEmbedding::shouldReceive('embed')->never();

        (new EmbeddingJob($vertical))->handle();

        $vertical->refresh();
        $this->assertFalse($vertical->isEmbedded());
    }

    public function test_embeds_text_and_persists_vector_in_transaction(): void
    {
        $source = Source::factory()->create(['description' => 'Text to embed for test.']);
        $fakeValues = Embeddings::fakeEmbedding(1536);
        $returnedVector = new Vector($fakeValues);

        TextEmbedding::shouldReceive('embed')
            ->once()
            ->with('Text to embed for test.')
            ->andReturn($returnedVector);

        (new EmbeddingJob($source))->handle();

        $source->refresh();
        $this->assertTrue($source->isEmbedded());
        $this->assertNotNull($source->getVector());
        $this->assertSame($returnedVector->toArray(), $source->getVector()->toArray());
    }

    public function test_uses_embedding_queue_name(): void
    {
        $source = Source::factory()->create();

        $job = new EmbeddingJob($source);

        $this->assertSame(EmbeddingJob::EMBEDDING_QUEUE->value, $job->queue);
    }

    public function test_unique_id_is_model_key(): void
    {
        $source = Source::factory()->create();

        $job = new EmbeddingJob($source);

        $this->assertSame((string) $source->getKey(), $job->uniqueId());
    }
}
