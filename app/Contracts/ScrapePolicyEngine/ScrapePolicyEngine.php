<?php

namespace App\Contracts\ScrapePolicyEngine;

use App\Models\Page;
use Carbon\CarbonInterface;

/**
 * Evaluates when a page should be scraped next and policy metrics.
 *
 * Implementations (e.g. Dummy, OpenAI) use page and snapshot data to compute
 * next_scrape_at and optional boosts/penalties stored in policy_result on the page.
 */
interface ScrapePolicyEngine
{
    /**
     * Evaluate the scraping policy for a page and return policy metrics.
     *
     * @param  Page  $page  The page to evaluate
     * @param  CarbonInterface|null  $baseTime  The base time to calculate from (defaults to now)
     * @return PolicyResult  The policy evaluation result containing the next scrape time and metrics
     */
    public function evaluate(Page $page, ?CarbonInterface $baseTime = null): PolicyResult;

    /**
     * Return the initial scraping time for the given page.
     */
    public function calculateInitialScrapingTime(Page $page): CarbonInterface;
}
