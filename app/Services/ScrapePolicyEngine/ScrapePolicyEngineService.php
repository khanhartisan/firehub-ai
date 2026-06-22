<?php

namespace App\Services\ScrapePolicyEngine;

use App\Contracts\ScrapePolicyEngine\PolicyResult;
use App\Contracts\ScrapePolicyEngine\ScrapePolicyEngine as ScrapePolicyEngineContract;
use App\Enums\Queue as QueueEnum;
use App\Enums\ScrapingStatus;
use App\Models\Page;
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

    public function calculateInitialScrapingTime(Page $page): CarbonInterface
    {
        if ($page->ignore_scraping_budget) {
            return now();
        }

        if ($page->next_scrape_at) {
            return $page->next_scrape_at;
        }

        $now = Carbon::now();

        return $this->applyPriorityBacklogDeferral(
            $this->resolveBudgetCandidate($page, $now),
            $now,
        );
    }

    protected function resolveBudgetCandidate(Page $page, CarbonInterface $now): CarbonInterface
    {
        if (! $source = $page->source) {
            return $now->copy();
        }

        if (! $this->sourceHasAnyBudget($source)) {
            return $now->copy();
        }

        $excludePageId = $page->exists ? (string) $page->getKey() : null;
        $candidate = $now->copy();

        for ($i = 0; $i < self::MAX_INITIAL_SCHEDULE_ITERATIONS; $i++) {
            $nextStarts = [];

            if ($source->daily_budget > 0) {
                $windowStart = $candidate->copy()->startOfDay();
                $windowEnd = $windowStart->copy()->addDay();
                if ($this->budgetUsageForSourceInWindow($source, $windowStart, $windowEnd, $excludePageId) >= $source->daily_budget) {
                    $nextStarts[] = $windowEnd;
                }
            }

            if ($source->weekly_budget > 0) {
                $windowStart = $candidate->copy()->startOfWeek(Carbon::MONDAY);
                $windowEnd = $windowStart->copy()->addWeek();
                if ($this->budgetUsageForSourceInWindow($source, $windowStart, $windowEnd, $excludePageId) >= $source->weekly_budget) {
                    $nextStarts[] = $windowEnd;
                }
            }

            if ($source->monthly_budget > 0) {
                $windowStart = $candidate->copy()->startOfMonth();
                $windowEnd = $windowStart->copy()->addMonth();
                if ($this->budgetUsageForSourceInWindow($source, $windowStart, $windowEnd, $excludePageId) >= $source->monthly_budget) {
                    $nextStarts[] = $windowEnd;
                }
            }

            if ($nextStarts === []) {
                return $candidate;
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

    /**
     * Priority pages bypass source budgets and can saturate scraping capacity.
     * Defer immediate scheduling for budget-respecting pages until backlog fits queue slots.
     */
    protected function applyPriorityBacklogDeferral(CarbonInterface $candidate, CarbonInterface $floor): CarbonInterface
    {
        $adjusted = $candidate->lt($floor) ? $floor->copy() : $candidate->copy();

        if ($adjusted->gt($floor)) {
            return $adjusted;
        }

        $slotsAvailable = QueueEnum::PAGE_SCRAPING->slotsAvailable();
        $backlog = $this->priorityScrapingBacklogCount();

        if ($backlog <= $slotsAvailable) {
            return $adjusted;
        }

        $excess = $backlog - $slotsAvailable;

        return $adjusted->copy()->addMinutes($excess * $this->priorityBacklogDeferMinutes());
    }

    protected function priorityScrapingBacklogCount(): int
    {
        return Page::query()
            ->where('ignore_scraping_budget', true)
            ->where(function ($query) {
                $query->whereIn('scraping_status', self::IN_FLIGHT_STATUSES)
                    ->orWhere(function ($query) {
                        $query->where('scraping_status', ScrapingStatus::PENDING)
                            ->where(function ($query) {
                                $query->whereNull('next_scrape_at')
                                    ->orWhere('next_scrape_at', '<=', now());
                            });
                    });
            })
            ->count();
    }

    protected function priorityBacklogDeferMinutes(): int
    {
        $minutes = $this->config['priority_backlog_defer_minutes']
            ?? config('scrapepolicyengine.priority_backlog_defer_minutes', 5);

        return max(1, (int) $minutes);
    }

    protected function sourceHasAnyBudget(Source $source): bool
    {
        return $source->daily_budget > 0
            || $source->weekly_budget > 0
            || $source->monthly_budget > 0;
    }

    /**
     * Usage = pages with a processed scrape timestamp in the window plus pages with a
     * scheduled next scrape in the window (each counted once per query; a page may contribute
     * to both if both timestamps fall in range).
     *
     * @param  CarbonInterface  $windowEndExclusive  inclusive upper bound (see callers)
     */
    protected function budgetUsageForSourceInWindow(
        Source $source,
        CarbonInterface $windowStart,
        CarbonInterface $windowEndExclusive,
        ?string $excludePageId = null,
    ): int {
        $scraped = $source
            ->pages()
            ->where('scraped_at', '>=', $windowStart)
            ->where('scraped_at', '<=', $windowEndExclusive)
            ->when($excludePageId !== null, fn ($q) => $q->where('id', '!=', $excludePageId));

        $scheduled = $source
            ->pages()
            ->where('next_scrape_at', '>=', $windowStart)
            ->where('next_scrape_at', '<=', $windowEndExclusive)
            ->when($excludePageId !== null, fn ($q) => $q->where('id', '!=', $excludePageId));

        return $scraped->count() + $scheduled->count();
    }

    /**
     * Evaluate the scraping policy for a page.
     */
    public function evaluate(Page $page, ?CarbonInterface $baseTime = null): PolicyResult
    {
        $baseTime = $baseTime ?? Carbon::now();

        return $this->performEvaluation($page, $baseTime);
    }

    /**
     * Perform the actual policy evaluation.
     * This method must be implemented by child classes.
     */
    abstract protected function performEvaluation(Page $page, Carbon $baseTime): PolicyResult;

    /**
     * Calculate cost factor based on snapshot data.
     * This is calculated from actual historical data, not AI inference.
     * Can be used by any driver that needs to calculate cost factor.
     */
    protected function calculateCostFactor(Page $page): float
    {
        // Query only the recent snapshots we need (last 10) to avoid loading all snapshots
        $recentSnapshots = $page->snapshots()
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
        $avgFilesCount = $recentSnapshots->avg('files_count') ?? 0;
        $avgFetchDuration = $recentSnapshots->whereNotNull('fetch_duration_ms')->avg('fetch_duration_ms') ?? 0;
        $avgStructuredDataCount = $recentSnapshots->avg('structured_data_count') ?? 0;

        // Normalize each metric to 0.0-1.0 range
        // These thresholds are reasonable defaults but could be made configurable
        $costNormalized = min(1.0, $avgCost / 10.0); // Assume max cost is $10.00
        $contentLengthNormalized = min(1.0, $avgContentLength / 1000000.0); // Assume max is 1MB
        $filesCountNormalized = min(1.0, $avgFilesCount / 100.0); // Assume max is 100 media items
        $fetchDurationNormalized = min(1.0, $avgFetchDuration / 30000.0); // Assume max is 30 seconds
        $structuredDataNormalized = min(1.0, $avgStructuredDataCount / 50.0); // Assume max is 50 structured data items

        // Weighted average: cost is most important, then content length, then other factors
        $costFactor = (
            $costNormalized * 0.4 +
            $contentLengthNormalized * 0.25 +
            $filesCountNormalized * 0.15 +
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
    protected function calculateErrorPenalty(Page $page): float
    {
        // Query only the recent snapshots we need (last 10) to avoid loading all snapshots
        $recentSnapshots = $page->snapshots()
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
    protected function calculateChangeBoost(Page $page): float
    {
        // Query only the recent snapshots we need (last 10) to avoid loading all snapshots
        $recentSnapshots = $page->snapshots()
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
