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
        $policyResult = ScrapePolicyEngine::evaluate($entity);

        $saved = null;
        DB::transaction(function () use ($entity, $policyResult, &$saved) {
            $entity->next_scrape_at = $policyResult->getNextScrapeAt();
            $entity->policy_result = $policyResult->toArray();
            $saved = $entity->save();
        });

        return $saved;
    }
}