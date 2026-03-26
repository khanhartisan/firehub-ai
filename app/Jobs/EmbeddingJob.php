<?php

namespace App\Jobs;

use App\Enums\Queue;
use App\Facades\TextEmbedding;
use App\Models\EmbeddableModel;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class EmbeddingJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    protected EmbeddableModel $embeddable;

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 60;

    public int $uniqueFor = 60;

    public const Queue EMBEDDING_QUEUE = Queue::DEFAULT;

    /**
     * Create a new job instance.
     */
    public function __construct(EmbeddableModel $embeddable)
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
        $embeddable = $this->embeddable;

        if (env('APP_DEBUG')) {
            dump('Embedding '.$embeddable->getMorphClass().': '.$embeddable->getKey());
        }

        if (!$embeddable->isEmbeddable()
            or $embeddable->isEmbedded()
            or !$textForEmbedding = $embeddable->getTextForEmbedding()
        ) {
            if (env('APP_DEBUG')) {
                dump('--- Skipping. Embeddable: '.($embeddable->isEmbeddable() ? 'true' : 'false').' / Embedded: '.($embeddable->isEmbedded() ? 'true' : 'false').' / Text for embedding: '.($textForEmbedding ?? '(undefined)'));
            }
            return;
        }

        $vector = TextEmbedding::embed($textForEmbedding);
        DB::transaction(fn () => $embeddable->setEmbedding($vector));
    }
}
