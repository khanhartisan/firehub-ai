<?php

namespace App\Jobs;

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

    /**
     * Create a new job instance.
     */
    public function __construct(EmbeddableModel $embeddable)
    {
        $this->embeddable = $embeddable->withoutRelations();
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
        if (!$embeddable->isEmbeddable()
            or $embeddable->isEmbedded()
            or !$textForEmbedding = $embeddable->getTextForEmbedding()
        ) {
            return;
        }

        $vector = TextEmbedding::embed($textForEmbedding);
        DB::transaction(fn () => $embeddable->setEmbedding($vector));
    }
}
