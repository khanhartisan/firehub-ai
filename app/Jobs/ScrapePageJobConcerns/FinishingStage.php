<?php

namespace App\Jobs\ScrapePageJobConcerns;

use App\Contracts\ScrapePolicyEngine\PolicyResult;
use App\Enums\ScrapingStatus;
use App\Facades\ScrapePolicyEngine;
use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait FinishingStage
{
    protected function handleFinishingStage(Page $page): bool
    {
        if (env('APP_DEBUG')) {
            dump('Finishing, page '.$page->id);
        }

        // Apply the next scrape at from policy
        $policy = $page->policy_result ?? [];
        $policy = PolicyResult::fromArray($policy);

        $initialScrapeAt = ScrapePolicyEngine::calculateInitialScrapingTime($page);

        $page->next_scrape_at = (
            $nextScrapeAt = $policy->getNextScrapeAt()
            and $nextScrapeAt->gt($initialScrapeAt)
        ) ? $nextScrapeAt
        : $initialScrapeAt;

        DB::transaction(fn () => $page->save());
        return true;
    }
}