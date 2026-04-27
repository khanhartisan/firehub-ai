<?php

namespace App\Jobs\ScrapePageJobConcerns;

use App\Contracts\ScrapePolicyEngine\PolicyResult;
use App\Enums\ScrapingStatus;
use App\Facades\ScrapePolicyEngine;
use App\Models\Page;
use App\Utils\Debugger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait FinishingStage
{
    protected function handleFinishingStage(Page $page): bool
    {
        Debugger::devConsoleDump('Finishing, page '.$page->id);

        // Apply the next scrape at from policy
        $policy = $page->policy_result ?? [];
        $policy = PolicyResult::fromArray($policy);

        $initialScrapeAt = ScrapePolicyEngine::calculateInitialScrapingTime($page);

        $page->ignore_scraping_budget = false;
        $page->next_scrape_at = (
            $nextScrapeAt = $policy->getNextScrapeAt()
            and $nextScrapeAt->gt($initialScrapeAt)
        ) ? $nextScrapeAt
        : $initialScrapeAt;

        Debugger::devConsoleDump('Next scrape at, page '.$page->id.': '.$page->next_scrape_at->diffForHumans());

        DB::transaction(fn () => $page->save());
        return true;
    }
}