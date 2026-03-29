<?php

namespace App\Facades;

use App\Models\Page;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\ScrapePolicyEngine\ScrapePolicyEngine driver(string|null $driver = null)
 * @method static \App\Contracts\ScrapePolicyEngine\PolicyResult evaluate(Page $page, ?CarbonInterface $baseTime = null)
 * @method static CarbonInterface calculateInitialScrapingTime(Page $page)
 *
 * @see \App\Services\ScrapePolicyEngine\ScrapePolicyEngineManager
 */
class ScrapePolicyEngine extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'scrape_policy_engine.manager';
    }
}
