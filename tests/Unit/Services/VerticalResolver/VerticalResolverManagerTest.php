<?php

namespace Tests\Unit\Services\VerticalResolver;

use App\Contracts\OpenAI\OpenAIClient;
use App\Facades\VerticalResolver;
use App\Services\OpenAI\OpenAIManager;
use App\Services\VerticalResolver\Drivers\OpenAICompatibleVerticalResolverDriver;
use App\Services\VerticalResolver\Drivers\KeywordVerticalResolverDriver;
use App\Services\VerticalResolver\Drivers\OpenAIVerticalResolverDriver;
use App\Services\VerticalResolver\VerticalResolverManager;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class VerticalResolverManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_default_driver(): void
    {
        Config::set('verticalresolver.default', 'openai');

        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $this->app->instance(OpenAIClient::class, $mockOpenAIClient);

        $manager = VerticalResolver::getFacadeRoot();

        $driver = $manager->driver();

        $this->assertInstanceOf(OpenAIVerticalResolverDriver::class, $driver);
    }

    public function test_it_returns_keyword_driver(): void
    {
        $manager = VerticalResolver::getFacadeRoot();

        $driver = $manager->driver('keyword');

        $this->assertInstanceOf(KeywordVerticalResolverDriver::class, $driver);
    }

    public function test_it_returns_openai_driver(): void
    {
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $this->app->instance(OpenAIClient::class, $mockOpenAIClient);

        $manager = VerticalResolver::getFacadeRoot();

        $driver = $manager->driver('openai');

        $this->assertInstanceOf(OpenAIVerticalResolverDriver::class, $driver);
    }

    public function test_it_returns_openai_compatible_driver(): void
    {
        $mockOpenAIManager = Mockery::mock(OpenAIManager::class);
        $mockOpenAIClient = Mockery::mock(\App\Contracts\OpenAI\OpenAIClient::class);
        $mockOpenAIManager->shouldReceive('driver')->with('openai_compatible')->andReturn($mockOpenAIClient);
        $this->app->instance('openai.manager', $mockOpenAIManager);

        $manager = VerticalResolver::getFacadeRoot();

        $driver = $manager->driver('openai_compatible');

        $this->assertInstanceOf(OpenAICompatibleVerticalResolverDriver::class, $driver);
    }

    public function test_get_default_driver_returns_configured_value(): void
    {
        Config::set('verticalresolver.default', 'keyword');

        $manager = VerticalResolver::getFacadeRoot();

        $this->assertEquals('keyword', $manager->getDefaultDriver());
    }

    public function test_facade_returns_vertical_resolver_manager_instance(): void
    {
        $manager = VerticalResolver::getFacadeRoot();

        $this->assertInstanceOf(VerticalResolverManager::class, $manager);
    }
}
