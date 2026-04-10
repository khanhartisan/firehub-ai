<?php

namespace Tests\Unit\Services\IntentResolver;

use App\Contracts\OpenAI\OpenAIClient;
use App\Facades\IntentResolver;
use App\Services\IntentResolver\Drivers\Gemma3IntentResolverDriver;
use App\Services\IntentResolver\Drivers\OpenAIIntentResolverDriver;
use App\Services\IntentResolver\IntentResolverManager;
use App\Services\OpenAI\OpenAIManager;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class IntentResolverManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_default_driver(): void
    {
        Config::set('intentresolver.default', 'openai');

        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $this->app->instance(OpenAIClient::class, $mockOpenAIClient);

        $manager = IntentResolver::getFacadeRoot();

        $driver = $manager->driver();

        $this->assertInstanceOf(OpenAIIntentResolverDriver::class, $driver);
    }

    public function test_it_returns_openai_driver(): void
    {
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $this->app->instance(OpenAIClient::class, $mockOpenAIClient);

        $manager = IntentResolver::getFacadeRoot();

        $driver = $manager->driver('openai');

        $this->assertInstanceOf(OpenAIIntentResolverDriver::class, $driver);
    }

    public function test_it_returns_gemma3_driver(): void
    {
        $mockOpenAIManager = Mockery::mock(OpenAIManager::class);
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockOpenAIManager->shouldReceive('driver')->with('gemma3')->andReturn($mockOpenAIClient);
        $this->app->instance('openai.manager', $mockOpenAIManager);

        $manager = IntentResolver::getFacadeRoot();

        $driver = $manager->driver('gemma3');

        $this->assertInstanceOf(Gemma3IntentResolverDriver::class, $driver);
    }

    public function test_get_default_driver_returns_configured_value(): void
    {
        Config::set('intentresolver.default', 'gemma3');

        $manager = IntentResolver::getFacadeRoot();

        $this->assertEquals('gemma3', $manager->getDefaultDriver());
    }

    public function test_facade_returns_intent_resolver_manager_instance(): void
    {
        $manager = IntentResolver::getFacadeRoot();

        $this->assertInstanceOf(IntentResolverManager::class, $manager);
    }
}
