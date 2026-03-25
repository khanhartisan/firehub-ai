<?php

namespace App\Facades;

use App\Models\Entity;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\ScrapePolicyEngine\ScrapePolicyEngine driver(string|null $driver = null)
 * @method static \App\Contracts\ScrapePolicyEngine\PolicyResult evaluate(Entity $entity, ?CarbonInterface $baseTime = null)
 * @method CarbonInterface calculateInitialScrapingTime(Entity $entity)
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
