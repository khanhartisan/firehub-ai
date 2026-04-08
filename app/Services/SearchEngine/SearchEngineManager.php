<?php

namespace App\Services\SearchEngine;

use App\Contracts\SearchEngine\SearchEngine;
use Illuminate\Support\Manager;

class SearchEngineManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('search_engine.default', 'google');
    }

    /**
     * Google web search. Implementation and provider are wired here; the driver config
     * only names the provider (see search_engine.drivers.google).
     */
    protected function createGoogleDriver(): SearchEngine
    {
        $driverConfig = $this->config->get('search_engine.drivers.google', []);
        $provider = $driverConfig['provider'] ?? 'searchapi';
        unset($driverConfig['provider']);

        $providerConfig = $this->config->get('search_engine.providers.'.$provider, []);

        $config = array_merge($providerConfig, $driverConfig);

        return new Drivers\SearchapiGoogleDriver($config);
    }
}
