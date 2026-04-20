<?php

namespace App\Jobs;

use App\Enums\KeywordStatus;
use App\Enums\Queue;
use App\Jobs\Concerns\HasManualLock;
use App\Models\Keyword;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class KeywordResearchJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;
    use HasManualLock;

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    protected Keyword $keyword;

    /**
     * Create a new job instance.
     */
    public function __construct(Keyword $keyword)
    {
        $this->keyword = $keyword->withoutRelations();

        $this->onQueue(Queue::KEYWORD_RESEARCHING->value);
    }

    public function uniqueId(): string
    {
        return $this->keyword->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $keyword = $this->keyword;
        $keyword->status = KeywordStatus::RESEARCHING;
        $keyword->save();

        // TODO: Implement keyword researching
    }
}
