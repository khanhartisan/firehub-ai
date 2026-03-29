<?php

namespace App\Jobs;

use App\Enums\ScrapingStage;
use App\Models\Page;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * This job is an intermediate dispatcher to help the ScrapePageJob dispatches
 * itself while it's still remaining the ShouldBeUnique behavior
 */
class ScrapePageJobDispatcher implements ShouldQueue
{
    use Queueable;

    protected Page $page;

    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(Page $page)
    {
        $this->page = $page->withoutRelations();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ScrapePageJob::dispatch($this->page);
    }
}
