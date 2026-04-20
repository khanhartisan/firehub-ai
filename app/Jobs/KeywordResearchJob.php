<?php

namespace App\Jobs;

use App\Contracts\SearchEngine\SearchOptions;
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

    public int $validityDuration = 86400;

    protected Keyword $keyword;

    /**
     * Create a new job instance.
     */
    public function __construct(Keyword $keyword, protected bool $forceResearch = false)
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
        if (!$lock = $this->getManualLock() or !$lock->get()) {
            return;
        }

        $keyword = $this->keyword;

        // Mark as researched
        if (!$this->forceResearch
            and $keyword->researched_at
            and abs(now()->diffInSeconds($keyword->researched_at)) <= $this->validityDuration
        ) {
            $keyword->status = KeywordStatus::RESEARCHED;
            $keyword->save();
            return;
        }

        // Mark keyword as researching
        $keyword->status = KeywordStatus::RESEARCHING;
        $keyword->save();

        // Build the search options
        $searchOptions = new SearchOptions();
        $searchOptions->setLanguage($keyword->language);
        $searchOptions->setCountry($keyword->country);

        // Perform search
        try {
            // TODO: Continue implementing
        } finally {
            $lock->release();
        }
    }
}
