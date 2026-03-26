<?php

namespace App\Jobs\ScrapeEntityJobConcerns;

use App\Contracts\ScrapePolicyEngine\PolicyResult;
use App\Enums\ScrapingStatus;
use App\Facades\ScrapePolicyEngine;
use App\Models\Entity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait FinishingStage
{
    protected function finish(Entity $entity): bool
    {
        if (env('APP_DEBUG')) {
            dump('Finishing, entity '.$entity->id);
        }

        // Apply the next scrape at from policy
        $policy = $entity->policy_result ?? [];
        $policy = PolicyResult::fromArray($policy);

        $initialScrapeAt = ScrapePolicyEngine::calculateInitialScrapingTime($entity);

        $entity->next_scrape_at = (
            $nextScrapeAt = $policy->getNextScrapeAt()
            and $nextScrapeAt->gt($initialScrapeAt)
        ) ? $nextScrapeAt
        : $initialScrapeAt;

        $this->markEntitySuccess($entity);
        return true;
    }
}