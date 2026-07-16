<?php

namespace Tests\Unit\Services\HitlGateway;

use App\Contracts\OpenAI\OpenAIClient;
use App\Facades\HitlGateway\HitlPlatformManager;
use App\Facades\HitlGateway\TaskAgent;
use App\Services\HitlGateway\HitlPlatformManagerDrivers\DummyHitlPlatformManager;
use App\Services\HitlGateway\HitlPlatformManagerManager;
use App\Services\HitlGateway\TaskAgentDrivers\DummyTaskAgent;
use App\Services\HitlGateway\TaskAgentDrivers\OpenAICompatibleTaskAgent;
use App\Services\HitlGateway\TaskAgentDrivers\OpenAITaskAgent;
use App\Services\HitlGateway\TaskAgentManager;
use App\Services\OpenAI\OpenAIManager;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class HitlGatewayManagersTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_platform_manager_facade_resolves_manager_and_default_driver(): void
    {
        Config::set('hitlgateway.platform_manager', 'dummy');

        $manager = HitlPlatformManager::getFacadeRoot();

        $this->assertInstanceOf(HitlPlatformManagerManager::class, $manager);
        $this->assertSame('dummy', $manager->getDefaultDriver());
        $this->assertInstanceOf(DummyHitlPlatformManager::class, $manager->driver());
    }

    public function test_task_agent_facade_resolves_manager_and_default_driver(): void
    {
        Config::set('hitlgateway.task_agent', 'dummy');

        $manager = TaskAgent::getFacadeRoot();

        $this->assertInstanceOf(TaskAgentManager::class, $manager);
        $this->assertSame('dummy', $manager->getDefaultDriver());
        $this->assertInstanceOf(DummyTaskAgent::class, $manager->driver());
    }

    public function test_task_agent_manager_returns_openai_driver(): void
    {
        $client = Mockery::mock(OpenAIClient::class);
        $this->app->instance(OpenAIClient::class, $client);

        $manager = TaskAgent::getFacadeRoot();

        $this->assertInstanceOf(OpenAITaskAgent::class, $manager->driver('openai'));
    }

    public function test_task_agent_manager_returns_openai_compatible_driver(): void
    {
        $openAIManager = Mockery::mock(OpenAIManager::class);
        $client = Mockery::mock(OpenAIClient::class);
        $openAIManager->shouldReceive('driver')->with('openai_compatible')->andReturn($client);
        $this->app->instance('openai.manager', $openAIManager);

        $manager = TaskAgent::getFacadeRoot();

        $this->assertInstanceOf(OpenAICompatibleTaskAgent::class, $manager->driver('openai_compatible'));
    }
}
