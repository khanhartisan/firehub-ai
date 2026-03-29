<?php

namespace App\Services\ScrapePolicyEngine\Drivers;

use App\Contracts\ScrapePolicyEngine\PolicyResult;
use App\Models\Page;
use App\Services\ScrapePolicyEngine\ScrapePolicyEngineService;
use Carbon\Carbon;

class DummyScrapePolicyEngineDriver extends ScrapePolicyEngineService
{
    /**
     * Perform policy evaluation with simple static results.
     */
    protected function performEvaluation(Page $page, Carbon $baseTime): PolicyResult
    {
        $result = new PolicyResult();

        // Simple static values for testing
        $result->setChangeBoost(0.5);
        $result->setValueBoost(0.5);
        $result->setErrorPenalty(0.0);
        $result->setPriority(0.5);
        $result->setUrgency(0.5);
        $result->setCostFactor(0.3);

        // Calculate next scrape time: default to 24 hours from base time
        $defaultIntervalHours = $this->config['default_interval_hours'] ?? 24;
        $nextScrapeAt = $baseTime->copy()->addHours($defaultIntervalHours);
        $result->setNextScrapeAt($nextScrapeAt);

        return $result;
    }
}
