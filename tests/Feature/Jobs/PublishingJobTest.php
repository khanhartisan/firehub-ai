<?php

namespace Tests\Feature\Jobs;

use App\Contracts\PlatformManager\FlyCms\FlyCms;
use App\Contracts\PlatformManager\PublishingResult;
use App\Enums\PlatformType;
use App\Enums\PublicationStatus;
use App\Enums\Queue;
use App\Facades\Platforms\FlyCms as FlyCmsFacade;
use App\Jobs\PublishingJob;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\Publication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PublishingJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_skips_when_publication_status_is_not_publishing(): void
    {
        $publication = $this->createPublication(PublicationStatus::PENDING);

        FlyCmsFacade::shouldReceive('driver')->never();

        (new PublishingJob($publication))->handle();

        $publication->refresh();
        $this->assertSame(PublicationStatus::PENDING, $publication->status);
        $this->assertSame(0, $publication->attempts);
    }

    public function test_marks_as_error_when_channel_is_missing(): void
    {
        $publication = $this->createPublication(PublicationStatus::PUBLISHING);
        DB::table('publications')->where('id', $publication->id)->update([
            'channel_id' => '01J0000000000000000000099',
        ]);
        $publication->refresh();

        (new PublishingJob($publication))->handle();

        $publication->refresh();
        $this->assertSame(PublicationStatus::PENDING, $publication->status);
        $this->assertSame(1, $publication->attempts);
        $this->assertStringContainsString('Missing or invalid channel.', (string) $publication->error_logs);
    }

    public function test_marks_as_error_when_platform_is_missing(): void
    {
        $client = $this->createClient();
        $channel = $this->createChannel($client, '01J0000000000000000000098');
        $publication = $this->createPublication(PublicationStatus::PUBLISHING, $channel);

        (new PublishingJob($publication))->handle();

        $publication->refresh();
        $this->assertSame(PublicationStatus::PENDING, $publication->status);
        $this->assertSame(1, $publication->attempts);
        $this->assertStringContainsString('Missing or invalid channel platform.', (string) $publication->error_logs);
    }

    public function test_publishes_via_flycms_and_saves_result(): void
    {
        $publication = $this->createFlyCmsPublication(PublicationStatus::PUBLISHING);

        $driver = Mockery::mock(FlyCms::class);
        $driver->shouldReceive('publishArticle')
            ->once()
            ->with(Mockery::on(fn (Publication $queuedPublication): bool => $queuedPublication->id === $publication->id))
            ->andReturn(new PublishingResult(
                PublicationStatus::PUBLISHED,
                '01J0000000000000000000051',
            ));
        FlyCmsFacade::shouldReceive('driver')->once()->andReturn($driver);

        (new PublishingJob($publication))->handle();

        $publication->refresh();
        $this->assertSame(PublicationStatus::PUBLISHED, $publication->status);
        $this->assertSame('01J0000000000000000000051', $publication->reference);
        $this->assertSame(1, $publication->attempts);
    }

    public function test_resets_failed_status_to_pending_when_attempts_remain(): void
    {
        $publication = $this->createFlyCmsPublication(PublicationStatus::PUBLISHING);

        $driver = Mockery::mock(FlyCms::class);
        $driver->shouldReceive('publishArticle')
            ->once()
            ->andReturn(new PublishingResult(
                PublicationStatus::FAILED,
                null,
                'Temporary publish failure.',
            ));
        FlyCmsFacade::shouldReceive('driver')->once()->andReturn($driver);

        (new PublishingJob($publication))->handle();

        $publication->refresh();
        $this->assertSame(PublicationStatus::PENDING, $publication->status);
        $this->assertSame(1, $publication->attempts);
        $this->assertStringContainsString('Temporary publish failure.', (string) $publication->error_logs);
    }

    public function test_marks_as_error_when_exception_thrown_during_publish(): void
    {
        $publication = $this->createFlyCmsPublication(PublicationStatus::PUBLISHING);

        $driver = Mockery::mock(FlyCms::class);
        $driver->shouldReceive('publishArticle')
            ->once()
            ->andThrow(new \RuntimeException('FlyCMS unavailable.'));
        FlyCmsFacade::shouldReceive('driver')->once()->andReturn($driver);

        (new PublishingJob($publication))->handle();

        $publication->refresh();
        $this->assertSame(PublicationStatus::PENDING, $publication->status);
        $this->assertSame(1, $publication->attempts);
        $this->assertStringContainsString('FlyCMS unavailable.', (string) $publication->error_logs);
    }

    public function test_marks_as_error_status_when_max_attempts_reached(): void
    {
        $publication = $this->createFlyCmsPublication(PublicationStatus::PUBLISHING, attempts: 4);

        $driver = Mockery::mock(FlyCms::class);
        $driver->shouldReceive('publishArticle')
            ->once()
            ->andReturn(new PublishingResult(
                PublicationStatus::FAILED,
                null,
                'Final publish failure.',
            ));
        FlyCmsFacade::shouldReceive('driver')->once()->andReturn($driver);

        (new PublishingJob($publication))->handle();

        $publication->refresh();
        $this->assertSame(PublicationStatus::FAILED, $publication->status);
        $this->assertSame(5, $publication->attempts);
    }

    public function test_marks_as_error_status_when_validation_errors_exhaust_retries(): void
    {
        $publication = $this->createPublication(PublicationStatus::PUBLISHING, attempts: 4);
        DB::table('publications')->where('id', $publication->id)->update([
            'channel_id' => '01J0000000000000000000099',
        ]);
        $publication->refresh();

        (new PublishingJob($publication))->handle();

        $publication->refresh();
        $this->assertSame(PublicationStatus::ERROR, $publication->status);
        $this->assertSame(5, $publication->attempts);
    }

    public function test_unique_id_is_publication_id(): void
    {
        $publication = $this->createPublication(PublicationStatus::PUBLISHING);

        $job = new PublishingJob($publication);

        $this->assertSame($publication->id, $job->uniqueId());
    }

    public function test_uses_publishing_queue_name(): void
    {
        $publication = $this->createPublication(PublicationStatus::PUBLISHING);

        $job = new PublishingJob($publication);

        $this->assertSame(Queue::PUBLISHING->value, $job->queue);
    }

    private function createClient(): Client
    {
        $client = new Client;
        $client->name = 'Acme Corp '.uniqid();
        $client->save();

        return $client;
    }

    private function createPlatform(PlatformType $type = PlatformType::FLYCMS): Platform
    {
        $platform = new Platform;
        $platform->name = 'Production FlyCMS '.uniqid();
        $platform->type = $type;
        $platform->save();

        return $platform;
    }

    private function createChannel(Client $client, Platform|string $platform): Channel
    {
        $platformId = $platform instanceof Platform ? $platform->id : $platform;

        $channel = new Channel;
        $channel->client()->associate($client);
        $channel->platform_id = $platformId;
        $channel->name = 'Blog';
        $channel->save();

        return $channel;
    }

    private function createFlyCmsPublication(
        PublicationStatus $status,
        int $attempts = 0,
    ): Publication {
        $client = $this->createClient();
        $platform = $this->createPlatform();
        $channel = $this->createChannel($client, $platform);

        return $this->createPublication($status, $channel, $attempts);
    }

    private function createPublication(
        PublicationStatus $status,
        ?Channel $channel = null,
        int $attempts = 0,
    ): Publication {
        if ($channel === null) {
            $client = $this->createClient();
            $platform = $this->createPlatform();
            $channel = $this->createChannel($client, $platform);
        }

        $publication = new Publication;
        $publication->channel()->associate($channel);
        $publication->publishable_type = 'article';
        $publication->publishable_id = (string) str()->ulid();
        $publication->status = $status;
        $publication->attempts = $attempts;
        $publication->save();

        return $publication;
    }
}
