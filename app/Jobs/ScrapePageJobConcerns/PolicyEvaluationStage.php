<?php

namespace App\Jobs\ScrapePageJobConcerns;

use App\Enums\ScrapingStatus;
use App\Facades\ScrapePolicyEngine;
use App\Models\Page;
use Illuminate\Support\Facades\DB;

trait PolicyEvaluationStage
{
    protected function evaluatePolicy(Page $page): bool
    {
        if (env('APP_DEBUG')) {
            dump('Evaluate policy, entity '.$page->id);
        }

        $policyResult = ScrapePolicyEngine::evaluate($page);

        $saved = null;
        DB::transaction(function () use ($page, $policyResult, &$saved) {
            $page->policy_result = $policyResult->toArray();
            $saved = $page->save();
        });

        return $saved;
    }
}