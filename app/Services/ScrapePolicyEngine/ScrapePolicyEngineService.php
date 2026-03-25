<?php

namespace App\Services\ScrapePolicyEngine;

use App\Contracts\ScrapePolicyEngine\PolicyResult;
use App\Contracts\ScrapePolicyEngine\ScrapePolicyEngine as ScrapePolicyEngineContract;
use App\Enums\ScrapingStatus;
use App\Models\Entity;
use App\Models\Snapshot;
use App\Models\Source;
use Carbon\Carbon;
use Carbon\CarbonInterface;

abstract class ScrapePolicyEngineService implements ScrapePolicyEngineContract
{
    /** @var list<ScrapingStatus> */
    protected const IN_FLIGHT_STATUSES = [
        ScrapingStatus::QUEUED,
        ScrapingStatus::FETCHING,
        ScrapingStatus::PROCESSING,
    ];

    private const int MAX_INITIAL_SCHEDULE_ITERATIONS = 10;

    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function calculateInitialScrapingTime(Entity $entity): CarbonInterface
    {
        if ($entity->next_scrape_at) {
            return $entity->next_scrape_at;
        }

        if (! $source = $entity->source) {
            return now();
        }

        if (! $this->sourceHasAnyBudget($source)) {
            return now();
        }

        $now = Carbon::now();
        $excludeEntityId = $entity->exists ? (string) $entity->getKey() : null;
        $candidate = $now->copy();

        for ($i = 0; $i < self::MAX_INITIAL_SCHEDULE_ITERATIONS; $i++) {
            $nextStarts = [];

            if ($source->daily_budget > 0) {
                $windowStart = $candidate->copy()->startOfDay();
                $windowEnd = $windowStart->copy()->addDay();
                if ($this->budgetUsageForSourceInWindow($source, $windowStart, $windowEnd, $excludeEntityId) >= $source->daily_budget) {
                    $nextStarts[] = $windowEnd;
                }
            }

            if ($source->weekly_budget > 0) {
                $windowStart = $candidate->copy()->startOfWeek(Carbon::MONDAY);
                $windowEnd = $windowStart->copy()->addWeek();
                if ($this->budgetUsageForSourceInWindow($source, $windowStart, $windowEnd, $excludeEntityId) >= $source->weekly_budget) {
                    $nextStarts[] = $windowEnd;
                }
            }

            if ($source->monthly_budget > 0) {
                $windowStart = $candidate->copy()->startOfMonth();
                $windowEnd = $windowStart->copy()->addMonth();
                if ($this->budgetUsageForSourceInWindow($source, $windowStart, $windowEnd, $excludeEntityId) >= $source->monthly_budget) {
                    $nextStarts[] = $windowEnd;
                }
            }

            if ($nextStarts === []) {
                return $candidate->lt($now) ? $now : $candidate;
            }

            $candidate = $nextStarts[0];
            foreach (array_slice($nextStarts, 1) as $nextStart) {
                if ($nextStart->gt($candidate)) {
                    $candidate = $nextStart;
                }
            }
        }

        return $candidate;
    }

    protected function sourceHasAnyBudget(Source $source): bool
    {
        return $source->daily_budget > 0
            || $source->weekly_budget > 0
            || $source->monthly_budget > 0;
    }

    /**
     * Usage = entities with a processed scrape timestamp in the window plus entities with a
     * scheduled next scrape in the window (each counted once per query; an entity may contribute
     * to both if both timestamps fall in range).
     *
     * @param  CarbonInterface  $windowEndExclusive  inclusive upper bound (see callers)
     */
    protected function budgetUsageForSourceInWindow(
        Source $source,
        CarbonInterface $windowStart,
        CarbonInterface $windowEndExclusive,
        ?string $excludeEntityId = null,
    ): int {
        $scraped = $source
            ->entities()
            ->where('scraped_at', '>=', $windowStart)
            ->where('scraped_at', '<=', $windowEndExclusive)
            ->when($excludeEntityId !== null, fn ($q) => $q->where('id', '!=', $excludeEntityId));

        $scheduled = $source
            ->entities()
            ->where('next_scrape_at', '>=', $windowStart)
            ->where('next_scrape_at', '<=', $windowEndExclusive)
            ->when($excludeEntityId !== null, fn ($q) => $q->where('id', '!=', $excludeEntityId));

        return $scraped->count() + $scheduled->count();
    }

    /**
     * Evaluate the scraping policy for an entity.
     */
    public function evaluate(Entity $entity, ?CarbonInterface $baseTime = null): PolicyResult
    {
        $baseTime = $baseTime ?? Carbon::now();

        return $this->performEvaluation($entity, $baseTime);
    }

    /**
     * Perform the actual policy evaluation.
     * This method must be implemented by child classes.
     */
    abstract protected function performEvaluation(Entity $entity, Carbon $baseTime): PolicyResult;

    /**
     * Calculate cost factor based on snapshot data.
     * This is calculated from actual historical data, not AI inference.
     * Can be used by any driver that needs to calculate cost factor.
     */
    protected function calculateCostFactor(Entity $entity): float
    {
        // Query only the recent snapshots we need (last 10) to avoid loading all snapshots
        $recentSnapshots = $entity->snapshots()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($recentSnapshots->isEmpty()) {
            // No historical data, return default moderate cost
            return 0.5;
        }

        // Calculate normalized metrics
        $avgCost = $recentSnapshots->whereNotNull('cost')->avg('cost') ?? 0.0;
        $avgContentLength = $recentSnapshots->whereNotNull('content_length')->avg('content_length') ?? 0;
        $avgMediaCount = $recentSnapshots->avg('media_count') ?? 0;
        $avgFetchDuration = $recentSnapshots->whereNotNull('fetch_duration_ms')->avg('fetch_duration_ms') ?? 0;
        $avgStructuredDataCount = $recentSnapshots->avg('structured_data_count') ?? 0;

        // Normalize each metric to 0.0-1.0 range
        // These thresholds are reasonable defaults but could be made configurable
        $costNormalized = min(1.0, $avgCost / 10.0); // Assume max cost is $10.00
        $contentLengthNormalized = min(1.0, $avgContentLength / 1000000.0); // Assume max is 1MB
        $mediaCountNormalized = min(1.0, $avgMediaCount / 100.0); // Assume max is 100 media items
        $fetchDurationNormalized = min(1.0, $avgFetchDuration / 30000.0); // Assume max is 30 seconds
        $structuredDataNormalized = min(1.0, $avgStructuredDataCount / 50.0); // Assume max is 50 structured data items

        // Weighted average: cost is most important, then content length, then other factors
        $costFactor = (
            $costNormalized * 0.4 +
            $contentLengthNormalized * 0.25 +
            $mediaCountNormalized * 0.15 +
            $fetchDurationNormalized * 0.1 +
            $structuredDataNormalized * 0.1
        );

        // Ensure it's between 0.0 and 1.0
        return max(0.0, min(1.0, $costFactor));
    }

    /**
     * Calculate error penalty factor based on snapshot data.
     * This is calculated from actual historical error rates, not AI inference.
     * Can be used by any driver that needs to calculate error penalty.
     */
    protected function calculateErrorPenalty(Entity $entity): float
    {
        // Query only the recent snapshots we need (last 10) to avoid loading all snapshots
        $recentSnapshots = $entity->snapshots()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($recentSnapshots->isEmpty()) {
            // No historical data, return default low error penalty
            return 0.0;
        }

        // Count error states (FAILED, TIMEOUT, BLOCKED)
        $errorStatuses = [
            \App\Enums\ScrapingStatus::FAILED->value,
            \App\Enums\ScrapingStatus::TIMEOUT->value,
            \App\Enums\ScrapingStatus::BLOCKED->value,
        ];

        $totalSnapshots = $recentSnapshots->count();
        $errorCount = $recentSnapshots->filter(function ($snapshot) use ($errorStatuses) {
            return in_array($snapshot->scraping_status->value, $errorStatuses);
        })->count();

        // Calculate error rate (0.0 to 1.0)
        $errorRate = $totalSnapshots > 0 ? $errorCount / $totalSnapshots : 0.0;

        // The error penalty is directly proportional to the error rate
        // Higher error rate = higher penalty
        return max(0.0, min(1.0, $errorRate));
    }

    /**
     * Calculate change boost factor based on snapshot data.
     * This is calculated from actual historical change percentages, not AI inference.
     * Can be used by any driver that needs to calculate change boost.
     */
    protected function calculateChangeBoost(Entity $entity): float
    {
        // Query only the recent snapshots we need (last 10) to avoid loading all snapshots
        $recentSnapshots = $entity->snapshots()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($recentSnapshots->isEmpty()) {
            // No historical data, return default moderate change boost
            return 0.5;
        }

        // Calculate average content change percentage from recent snapshots
        $changePercentages = $recentSnapshots->whereNotNull('content_change_percentage')
            ->pluck('content_change_percentage')
            ->toArray();

        if (empty($changePercentages)) {
            // No change data available, return default moderate change boost
            return 0.5;
        }

        $avgChangePercentage = array_sum($changePercentages) / count($changePercentages);

        // Normalize change percentage to 0.0-1.0 range
        // Change percentage is already 0-100, so divide by 100 to get 0.0-1.0
        // Higher change percentage = higher change boost
        $changeBoost = min(1.0, $avgChangePercentage / 100.0);

        // Ensure it's between 0.0 and 1.0
        return max(0.0, min(1.0, $changeBoost));
    }
}
