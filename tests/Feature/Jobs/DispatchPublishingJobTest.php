<?php

namespace Tests\Feature\Jobs;

use App\Enums\PlatformType;
use App\Enums\PublicationStatus;
use App\Enums\Queue as QueueEnum;
use App\Jobs\DispatchPublishingJob;
use App\Jobs\PublishingJob;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class DispatchPublishingJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.max_publishing_queue_size', 1000);
    }

    public function test_dispatches_publishing_job_for_oldest_pending_publication_first(): void
    {
        Queue::fake();

        $older = $this->createPublication(PublicationStatus::PENDING, updatedAt: now()->subHour());
        $newer = $this->createPublication(PublicationStatus::PENDING, updatedAt: now()->subMinutes(10));

        (new DispatchPublishingJob)->handle();

        Queue::assertPushed(PublishingJob::class, 2);

        $dispatchedPublicationIds = collect(Queue::pushed(PublishingJob::class))
            ->map(fn (PublishingJob $job): string => $this->getJobPublicationId($job))
            ->values()
            ->all();

        $this->assertSame([$older->id, $newer->id], $dispatchedPublicationIds);

        $older->refresh();
        $newer->refresh();
        $this->assertSame(PublicationStatus::PUBLISHING, $older->status);
        $this->assertSame(PublicationStatus::PUBLISHING, $newer->status);
    }

    public function test_dispatches_for_timeout_publication_when_no_pending_exists(): void
    {
        Queue::fake();

        $timedOut = $this->createPublication(PublicationStatus::TIMEOUT);

        (new DispatchPublishingJob)->handle();

        Queue::assertPushed(PublishingJob::class, 1);

        $timedOut->refresh();
        $this->assertSame(PublicationStatus::PUBLISHING, $timedOut->status);
    }

    public function test_does_not_dispatch_when_publishing_queue_is_full(): void
    {
        Config::set('queue.max_publishing_queue_size', 0);
        Queue::fake();

        $publication = $this->createPublication(PublicationStatus::PENDING);

        (new DispatchPublishingJob)->handle();

        Queue::assertNotPushed(PublishingJob::class);

        $publication->refresh();
        $this->assertSame(PublicationStatus::PENDING, $publication->status);
    }

    public function test_does_not_dispatch_when_no_eligible_publications_exist(): void
    {
        Queue::fake();

        $this->createPublication(PublicationStatus::PUBLISHED);
        $this->createPublication(PublicationStatus::PUBLISHING);

        (new DispatchPublishingJob)->handle();

        Queue::assertNotPushed(PublishingJob::class);
    }

    public function test_dispatches_multiple_publications_until_no_work_remains(): void
    {
        Queue::fake();

        $first = $this->createPublication(PublicationStatus::PENDING, updatedAt: now()->subHours(2));
        $second = $this->createPublication(PublicationStatus::PENDING, updatedAt: now()->subHour());

        (new DispatchPublishingJob)->handle();

        Queue::assertPushed(PublishingJob::class, 2);

        $first->refresh();
        $second->refresh();
        $this->assertSame(PublicationStatus::PUBLISHING, $first->status);
        $this->assertSame(PublicationStatus::PUBLISHING, $second->status);
    }

    public function test_handle_status_throws_for_publishing_status(): void
    {
        $job = new DispatchPublishingJob;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid publication status to handle.');

        $this->invokeHandleStatus($job, PublicationStatus::PUBLISHING);
    }

    public function test_uses_scheduler_queue_name(): void
    {
        $job = new DispatchPublishingJob;

        $this->assertSame(QueueEnum::SCHEDULER->value, $job->queue);
    }

    private function createPublication(
        PublicationStatus $status,
        ?\DateTimeInterface $updatedAt = null,
    ): Publication {
        $client = new Client;
        $client->name = 'Acme Corp '.uniqid();
        $client->save();

        $platform = new Platform;
        $platform->name = 'Production FlyCMS '.uniqid();
        $platform->type = PlatformType::FLYCMS;
        $platform->save();

        $channel = new Channel;
        $channel->client()->associate($client);
        $channel->platform()->associate($platform);
        $channel->reference = sha1(uniqid());
        $channel->name = 'Blog';
        $channel->save();

        $publication = new Publication;
        $publication->channel()->associate($channel);
        $publication->publishable_type = 'article';
        $publication->publishable_id = (string) str()->ulid();
        $publication->status = $status;
        $publication->save();

        if ($updatedAt !== null) {
            DB::table('publications')->where('id', $publication->id)->update([
                'updated_at' => $updatedAt,
            ]);
            $publication->refresh();
        }

        return $publication;
    }

    private function invokeHandleStatus(DispatchPublishingJob $job, PublicationStatus $status): bool
    {
        $method = new \ReflectionMethod($job, 'handleStatus');
        $method->setAccessible(true);

        return $method->invoke($job, $status);
    }

    private function getJobPublicationId(PublishingJob $job): string
    {
        $property = new \ReflectionProperty($job, 'publication');
        $property->setAccessible(true);

        /** @var Publication $publication */
        $publication = $property->getValue($job);

        return $publication->id;
    }
}
