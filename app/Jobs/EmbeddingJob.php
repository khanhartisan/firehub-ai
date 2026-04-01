<?php

namespace App\Jobs;

use App\Enums\Queue;
use App\Facades\TextEmbedding;
use App\Models\EmbeddableModel;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EmbeddingJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    protected EmbeddableModel $embeddable;

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 60;

    public int $uniqueFor = 60;

    public const Queue EMBEDDING_QUEUE = Queue::DEFAULT;

    protected Lock $manualLock;

    /**
     * Create a new job instance.
     */
    public function __construct(EmbeddableModel $embeddable, protected bool $force = false)
    {
        $this->embeddable = $embeddable->withoutRelations();

        $this->onQueue(static::EMBEDDING_QUEUE->value);
    }

    public function uniqueId(): string
    {
        return $this->embeddable->getKey();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Manual lock
        $lock = $this->getManualLock();
        if (!$this->force and !$lock->get()) {
            return;
        }

        $embeddable = $this->embeddable;

        if (env('APP_DEBUG')) {
            dump('Embedding '.$embeddable->getMorphClass().': '.$embeddable->getKey());
        }

        try {

            if (!$embeddable->isEmbeddable()
                or $embeddable->isEmbedded()
                or !$textForEmbedding = $embeddable->getTextForEmbedding()
            ) {
                if (env('APP_DEBUG')) {
                    dump('--- Skipping. Embeddable: '.($embeddable->isEmbeddable() ? 'true' : 'false').' / Embedded: '.($embeddable->isEmbedded() ? 'true' : 'false').' / Text for embedding: '.($textForEmbedding ?? '(undefined)'));
                }
                $lock->release();
                return;
            }

            $vector = TextEmbedding::embed($textForEmbedding);
            DB::transaction(fn () => $embeddable->setEmbedding($vector));

        } finally {
            $lock->release();
        }
    }

    protected function getManualLock(): Lock
    {
        return $this->manualLock ??= Cache::lock(sha1(static::class.'@manual-lock@'.$this->uniqueId()), $this->uniqueFor);
    }
}
