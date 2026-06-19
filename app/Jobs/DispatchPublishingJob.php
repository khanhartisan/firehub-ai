<?php

namespace App\Jobs;

use App\Enums\PublicationStatus;
use App\Enums\Queue;
use App\Models\Publication;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchPublishingJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 60;

    public int $uniqueFor = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue(Queue::SCHEDULER->value);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = time();
        while (true) {
            if (time() - $startTime >= $this->timeout - 5) {
                return;
            }

            if (!Queue::PUBLISHING->canDispatch()) {
                return;
            }

            if (!$this->handleStatus(PublicationStatus::PENDING)
                and !$this->handleStatus(PublicationStatus::TIMEOUT)
            ) {
                return;
            }
        }
    }

    protected function handleStatus(PublicationStatus $status): bool
    {
        if ($status === PublicationStatus::PUBLISHING) {
            throw new \InvalidArgumentException('Invalid publication status to handle.');
        }

        if (!$publication =
            Publication::query()
                ->where('status', $status)
                ->orderBy('updated_at')
                ->first()
        ) {
            return false;
        }

        $publication->status = PublicationStatus::PUBLISHING;
        if ($publication->saveQuietly()) {
            PublishingJob::dispatch($publication);
            return true;
        }

        return false;
    }
}
