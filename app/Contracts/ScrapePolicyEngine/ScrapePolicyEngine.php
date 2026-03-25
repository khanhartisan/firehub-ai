<?php

namespace App\Contracts\ScrapePolicyEngine;

use App\Models\Entity;
use Carbon\CarbonInterface;

/**
 * Evaluates when an entity should be scraped next and policy metrics.
 *
 * Implementations (e.g. Dummy, OpenAI) use entity and snapshot data to compute
 * next_scrape_at and optional boosts/penalties stored in policy_result on the entity.
 */
interface ScrapePolicyEngine
{
    /**
     * Evaluate the scraping policy for an entity and return policy metrics.
     *
     * @param  Entity  $entity  The entity to evaluate
     * @param  CarbonInterface|null  $baseTime  The base time to calculate from (defaults to now)
     * @return PolicyResult  The policy evaluation result containing the next scrape time and metrics
     */
    public function evaluate(Entity $entity, ?CarbonInterface $baseTime = null): PolicyResult;

    /**
     * Return the initial scraping time for the given entity.
     *
     * @param Entity $entity
     * @return CarbonInterface
     */
    public function calculateInitialScrapingTime(Entity $entity): CarbonInterface;
}
