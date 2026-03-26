<?php

namespace App\Jobs\ScrapeEntityJobConcerns;

use App\Enums\ScrapingStatus;
use App\Facades\ScrapePolicyEngine;
use App\Models\Entity;
use Illuminate\Support\Facades\DB;

trait PolicyEvaluationStage
{
    protected function evaluatePolicy(Entity $entity): bool
    {
        if (env('APP_DEBUG')) {
            dump('Evaluate policy, entity '.$entity->id);
        }

        $policyResult = ScrapePolicyEngine::evaluate($entity);
        $initialScrapingTime = ScrapePolicyEngine::calculateInitialScrapingTime($entity);

        $saved = null;
        DB::transaction(function () use ($entity, $policyResult, $initialScrapingTime, &$saved) {
            $entity->next_scrape_at = ($policyResult->getNextScrapeAt() > $initialScrapingTime)
                ? $policyResult->getNextScrapeAt()
                : $initialScrapingTime;
            $entity->policy_result = $policyResult->toArray();
            $saved = $entity->save();
        });

        return $saved;
    }
}