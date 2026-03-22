<?php

namespace Tests\Feature\Jobs;

use App\Jobs\EmbeddingJob;
use App\Jobs\ScheduleEmbeddingJob;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleEmbeddingJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('queue.max_default_queue_size', 1000);
    }

    public function test_dispatches_embedding_jobs_for_each_unembedded_embeddable_source(): void
    {
        Queue::fake();

        Source::factory()->count(2)->create();

        (new ScheduleEmbeddingJob(perModelLimit: 10))->handle();

        Queue::assertPushed(EmbeddingJob::class, 2);
    }

    public function test_respects_per_model_limit_for_sources(): void
    {
        Queue::fake();

        Source::factory()->count(5)->create();

        (new ScheduleEmbeddingJob(perModelLimit: 2))->handle();

        Queue::assertPushed(EmbeddingJob::class, 2);
    }

    public function test_does_not_dispatch_embedding_jobs_when_per_model_limit_is_zero(): void
    {
        Queue::fake();

        Source::factory()->create();

        (new ScheduleEmbeddingJob(perModelLimit: 0))->handle();

        Queue::assertNotPushed(EmbeddingJob::class);
    }

    public function test_skips_work_when_cache_lock_is_already_held(): void
    {
        Queue::fake();

        Source::factory()->create();

        $lock = Cache::lock('schedule-embedding', 10);
        $this->assertTrue($lock->get());

        try {
            (new ScheduleEmbeddingJob(perModelLimit: 10))->handle();

            Queue::assertNotPushed(EmbeddingJob::class);
            Queue::assertNotPushed(ScheduleEmbeddingJob::class);
        } finally {
            $lock->release();
        }
    }

    public function test_self_dispatches_when_default_queue_can_accept_jobs(): void
    {
        Queue::fake();

        Source::factory()->create();

        (new ScheduleEmbeddingJob(perModelLimit: 10))->handle();

        Queue::assertPushed(ScheduleEmbeddingJob::class, 1);
    }

    public function test_does_not_self_dispatch_when_default_queue_is_full(): void
    {
        Config::set('queue.max_default_queue_size', 0);
        Queue::fake();

        Source::factory()->create();

        (new ScheduleEmbeddingJob(perModelLimit: 10))->handle();

        Queue::assertNotPushed(ScheduleEmbeddingJob::class);
    }
}
