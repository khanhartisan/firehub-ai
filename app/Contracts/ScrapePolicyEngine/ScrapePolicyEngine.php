<?php

namespace App\Contracts\ScrapePolicyEngine;

use App\Models\Entity;
use Carbon\Carbon;

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
     * @param  Carbon|null  $baseTime  The base time to calculate from (defaults to now)
     * @return PolicyResult  The policy evaluation result containing next scrape time and metrics
     */
    public function evaluate(Entity $entity, ?Carbon $baseTime = null): PolicyResult;
}
