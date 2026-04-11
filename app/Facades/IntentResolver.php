<?php

namespace App\Facades;

use App\Services\IntentResolver\IntentResolverManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\IntentResolver\IntentableIntents resolve(\App\Contracts\IntentResolver\Intentable $intentable)
 * @method static list<\App\Contracts\IntentResolver\IntentKeyword> guessIntentKeywords(\App\Contracts\IntentResolver\Intent $intentData)
 * @method static list<\App\Contracts\IntentResolver\IntentKeywords> inferFromKeywords(array $keywords)
 * @method static list<\App\Contracts\IntentResolver\IntentKeyword> scoreKeywords(\App\Contracts\IntentResolver\Intent $intentData, array $keywords)
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
