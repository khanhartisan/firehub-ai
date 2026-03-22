<?php

namespace App\Jobs;

use App\Enums\Queue as QueueEnum;
use App\Models\EmbeddableModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ScheduleEmbeddingJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Only one execution at a time across workers (complements queue uniqueness).
     */
    private const string LOCK_KEY = 'schedule-embedding';

    /**
     * Max seconds to hold the lock if the job exits without releasing.
     */
    private const int LOCK_SECONDS = 300;

    public int $uniqueFor = 300;

    /**
     * @param  int  $perModelLimit  Max unembedded rows to queue per morph-mapped EmbeddableModel subclass (capped at 1000).
     */
    public function __construct(public int $perModelLimit = 100)
    {
        $this->onQueue(QueueEnum::SCHEDULER->value);
    }

    public function uniqueId(): string
    {
        return 'schedule-embedding';
    }

    /**
     * ShouldBeUniqueUntilProcessing limits queued duplicates; Cache::lock ensures
     * only one worker runs this logic at a time.
     */
    public function handle(): void
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (!$lock->get()) {
            return;
        }

        try {
            $this->runScheduler();

            if (EmbeddingJob::EMBEDDING_QUEUE->canDispatch()) {
                static::dispatch($this->perModelLimit)->delay(now()->addSecond());
            }
        } finally {
            $lock->release();
        }
    }

    private function runScheduler(): void
    {
        $morphMap = Relation::morphMap() ?? [];

        foreach ($morphMap as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, EmbeddableModel::class)) {
                continue;
            }

            $limit = min(max($this->perModelLimit, 0), 1000);
            if ($limit === 0) {
                continue;
            }

            $slotsAvailable = min(
                $limit,
                EmbeddingJob::EMBEDDING_QUEUE->slotsAvailable()
            );

            // Stop if the queue is full
            if ($slotsAvailable < 1) {
                return;
            }

            /** @var class-string<EmbeddableModel> $class */
            foreach ($class::getUnembedded($slotsAvailable) as $embeddableModel) {
                dispatch(new EmbeddingJob($embeddableModel));
            }
        }
    }
}
