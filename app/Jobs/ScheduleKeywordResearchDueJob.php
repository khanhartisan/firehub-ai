<?php

namespace App\Jobs;

use App\Enums\KeywordStatus;
use App\Enums\Queue as QueueEnum;
use App\Models\Keyword;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScheduleKeywordResearchDueJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue(QueueEnum::SCHEDULER->value);
    }

    public function uniqueId(): string
    {
        return 'schedule-keyword-research-due';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $slotsAvailable = QueueEnum::KEYWORD_RESEARCHING->slotsAvailable();
        if ($slotsAvailable < 1) {
            return;
        }

        $keywords = Keyword::query()
            ->where('status', KeywordStatus::RESEARCHING)
            ->where('updated_at', '<=', now()->subMinutes(15))
            ->orderBy('updated_at')
            ->limit($slotsAvailable)
            ->get();

        foreach ($keywords as $keyword) {
            // Touch first to avoid immediate rescheduling loops.
            $keyword->touchQuietly();
            KeywordResearchJob::dispatch($keyword);
        }
    }
}
