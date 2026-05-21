<?php

namespace Tests\Unit\Services\ScrapePolicyEngine;

use App\Contracts\OpenAI\OpenAIClient;
use App\Facades\ScrapePolicyEngine;
use App\Services\OpenAI\OpenAIManager;
use App\Services\ScrapePolicyEngine\Drivers\DummyScrapePolicyEngineDriver;
use App\Services\ScrapePolicyEngine\Drivers\OpenAICompatibleScrapePolicyEngineDriver;
use App\Services\ScrapePolicyEngine\Drivers\OpenAIScrapePolicyEngineDriver;
use App\Services\ScrapePolicyEngine\ScrapePolicyEngineManager;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class ScrapePolicyEngineManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_default_driver(): void
    {
        Config::set('scrapepolicyengine.default', 'dummy');

        $manager = ScrapePolicyEngine::getFacadeRoot();

        $driver = $manager->driver();

        $this->assertInstanceOf(DummyScrapePolicyEngineDriver::class, $driver);
    }

    public function test_it_returns_openai_driver(): void
    {
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $this->app->instance(OpenAIClient::class, $mockOpenAIClient);

        $manager = ScrapePolicyEngine::getFacadeRoot();

        $driver = $manager->driver('openai');

        $this->assertInstanceOf(OpenAIScrapePolicyEngineDriver::class, $driver);
    }

    public function test_it_returns_openai_compatible_driver(): void
    {
        $mockOpenAIManager = Mockery::mock(OpenAIManager::class);
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockOpenAIManager->shouldReceive('driver')->with('openai_compatible')->andReturn($mockOpenAIClient);
        $this->app->instance('openai.manager', $mockOpenAIManager);

        $manager = ScrapePolicyEngine::getFacadeRoot();

        $driver = $manager->driver('openai_compatible');

        $this->assertInstanceOf(OpenAICompatibleScrapePolicyEngineDriver::class, $driver);
    }

    public function test_get_default_driver_returns_configured_value(): void
    {
        Config::set('scrapepolicyengine.default', 'dummy');

        $manager = ScrapePolicyEngine::getFacadeRoot();

        $this->assertEquals('dummy', $manager->getDefaultDriver());
    }

    public function test_facade_returns_scrape_policy_engine_manager_instance(): void
    {
        $manager = ScrapePolicyEngine::getFacadeRoot();

        $this->assertInstanceOf(ScrapePolicyEngineManager::class, $manager);
    }
}
