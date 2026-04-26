<?php

namespace Tests\Unit\Services\SearchEngine;

use App\Facades\SearchEngine;
use App\Services\SearchEngine\Drivers\PerplexitySearchDriver;
use App\Services\SearchEngine\Drivers\SearchapiGoogleDriver;
use App\Services\SearchEngine\SearchEngineManager;
use Illuminate\Support\Facades\Config;
use ReflectionClass;
use Tests\TestCase;

class SearchEngineManagerTest extends TestCase
{
    protected function manager(): SearchEngineManager
    {
        return app('search_engine.manager');
    }

    public function test_it_returns_default_google_driver(): void
    {
        Config::set('search_engine.default', 'google');

        $driver = $this->manager()->driver();

        $this->assertInstanceOf(SearchapiGoogleDriver::class, $driver);
    }

    public function test_it_returns_google_driver_when_requested_explicitly(): void
    {
        $driver = $this->manager()->driver('google');

        $this->assertInstanceOf(SearchapiGoogleDriver::class, $driver);
    }

    public function test_it_returns_perplexity_driver_when_requested_explicitly(): void
    {
        $driver = $this->manager()->driver('perplexity');

        $this->assertInstanceOf(PerplexitySearchDriver::class, $driver);
    }

    public function test_get_default_driver_reads_config(): void
    {
        Config::set('search_engine.default', 'google');

        $this->assertSame('google', $this->manager()->getDefaultDriver());
    }

    public function test_google_driver_merges_provider_config_with_driver_overrides(): void
    {
        Config::set('search_engine.providers.searchapi', [
            'api_key' => 'provider-key',
            'base_url' => 'https://provider.example/',
            'timeout' => 30,
            'connect_timeout' => 5,
        ]);
        Config::set('search_engine.drivers.google', [
            'provider' => 'searchapi',
            'timeout' => 77,
        ]);

        $driver = $this->manager()->driver('google');

        $ref = new ReflectionClass($driver);
        $prop = $ref->getProperty('config');
        $prop->setAccessible(true);
        /** @var array<string, mixed> $merged */
        $merged = $prop->getValue($driver);

        $this->assertSame('provider-key', $merged['api_key']);
        $this->assertSame('https://provider.example/', $merged['base_url']);
        $this->assertSame(77, $merged['timeout']);
        $this->assertSame(5, $merged['connect_timeout']);
    }

    public function test_facade_root_is_search_engine_manager(): void
    {
        $manager = SearchEngine::getFacadeRoot();

        $this->assertInstanceOf(SearchEngineManager::class, $manager);
    }

    public function test_perplexity_driver_merges_provider_config_with_driver_overrides(): void
    {
        Config::set('search_engine.providers.perplexity', [
            'api_key' => 'provider-key',
            'base_url' => 'https://api.perplexity.ai/',
            'model' => 'sonar',
            'timeout' => 30,
            'connect_timeout' => 5,
        ]);
        Config::set('search_engine.drivers.perplexity', [
            'provider' => 'perplexity',
            'model' => 'sonar-pro',
        ]);

        $driver = $this->manager()->driver('perplexity');

        $ref = new ReflectionClass($driver);
        $prop = $ref->getProperty('config');
        $prop->setAccessible(true);
        /** @var array<string, mixed> $merged */
        $merged = $prop->getValue($driver);

        $this->assertSame('provider-key', $merged['api_key']);
        $this->assertSame('https://api.perplexity.ai/', $merged['base_url']);
        $this->assertSame('sonar-pro', $merged['model']);
        $this->assertSame(30, $merged['timeout']);
        $this->assertSame(5, $merged['connect_timeout']);
    }
}
