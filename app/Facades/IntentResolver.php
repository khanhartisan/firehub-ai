<?php

namespace App\Facades;

use App\Services\IntentResolver\IntentResolverManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\IntentResolver\IntentData resolve(string $content)
 * @method static list<\App\Contracts\IntentResolver\IntentKeywordData> guessKeywords(\App\Contracts\IntentResolver\IntentData $intentData)
 * @method static \App\Contracts\IntentResolver\IntentResolver driver(string|null $driver = null)
 *
 * @see IntentResolverManager
 */
class IntentResolver extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'intent_resolver.manager';
    }
}
