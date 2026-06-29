<?php

namespace App\Jobs;

use App\Contracts\PlatformManager\PublishingResult;
use App\Enums\PlatformType;
use App\Enums\PublicationStatus;
use App\Enums\Queue;
use App\Facades\Platforms\FlyCms;
use App\Models\Article;
use App\Models\Channel;
use App\Models\Platform;
use App\Models\Publication;
use App\Utils\Str;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PublishingJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 60;

    public int $uniqueFor = 60;

    public bool $deleteWhenMissingModels = true;

    protected Publication $publication;

    public int $maxPublishingAttempts = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(Publication $publication)
    {
        $this->publication = $publication->withoutRelations();

        $this->onQueue(Queue::PUBLISHING->value);
    }

    public function uniqueId(): string
    {
        return $this->publication->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $publication = $this->publication;
        if ($publication->status !== PublicationStatus::PUBLISHING) {
            return;
        }

        /** @var Channel $channel */
        if (!$channel = $publication->channel
            or !$channel instanceof Channel
        ) {
            $this->markAsError('Missing or invalid channel.');
            return;
        }

        /** @var Platform $platform */
        if (!$platform = $channel->platform
            or !$platform instanceof Platform
        ) {
            $this->markAsError('Missing or invalid channel platform.');
            return;
        }

        switch ($platform->type) {
            case PlatformType::FLYCMS:
                $this->handleFlyCms();
                break;

            default:
                $this->markAsError('Unknown platform.');
                break;
        }
    }

    protected function handleFlyCms(): void
    {
        $this->handlePlatform(function () {
            $flycms = FlyCms::driver();

            $publication = $this->publication;

            /** @var Channel $channel */
            $channel = $publication->channel;

            /** @var Platform $platform */
            $platform = $channel->platform;
            if ($platformConfig = $platform->config) {
                $flycms->setConfig($platformConfig);
            }

            $this->saveWithPublishingResult(
                $flycms->publishArticle($publication)
            );

            if ($publication->status === PublicationStatus::PUBLISHED) {
                $publication->attempts = 0;
                $publication->saveQuietly();
            }
        });
    }

    protected function handlePlatform(\Closure $handler): void
    {
        try {
            $handler();
        } catch (\Exception $e) {
            $this->markAsError($e->getMessage()."\n".$e->getTraceAsString());
        }
    }

    protected function markAsError(string $logs): bool
    {
        $publication = $this->publication;
        $publication->attempts++;

        $publication->status = $publication->attempts < $this->maxPublishingAttempts
            ? PublicationStatus::PENDING
            : PublicationStatus::ERROR;
        $publication->error_logs = Str::appendLimit(
            $publication->error_logs ?? '',
            "\n---\n".$logs,
            10000
        );

        return $publication->save();
    }

    protected function saveWithPublishingResult(PublishingResult $publishingResult): bool
    {
        $publication = $this->publication;
        $publication->attempts++;
        $publication->status = $publishingResult->getStatus();
        $publication->reference = $publishingResult->getStatus() === PublicationStatus::PUBLISHED
            ? $publishingResult->getReference()
            : $publication->reference;
        $publication->error_logs = Str::appendLimit(
            $publication->error_logs ?? '',
            "\n---\n".$publishingResult->getErrorLogs(),
            10000
        );

        if (in_array($publication->status, [
            PublicationStatus::FAILED,
            PublicationStatus::ERROR
        ]) and $publication->attempts < $this->maxPublishingAttempts) {
            $publication->status = PublicationStatus::PENDING;
        }

        return $publication->save();
    }
}
