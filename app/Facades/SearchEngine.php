<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Contracts\SearchEngine\SearchResults search(string $query, ?\App\Contracts\SearchEngine\SearchOptions $options = null)
 * @method static \App\Contracts\SearchEngine\SearchEngine driver(string|null $driver = null)
 *
 * @see \App\Services\SearchEngine\SearchEngineManager
 */
class SearchEngine extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'search_engine.manager';
    }
}
