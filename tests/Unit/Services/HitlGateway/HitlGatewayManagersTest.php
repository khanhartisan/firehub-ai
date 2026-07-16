<?php

namespace Tests\Unit\Services\HitlGateway;

use App\Facades\HitlGateway\HitlPlatformManager;
use App\Facades\HitlGateway\TaskAgent;
use App\Services\HitlGateway\HitlPlatformManagerDrivers\DummyHitlPlatformManager;
use App\Services\HitlGateway\HitlPlatformManagerManager;
use App\Services\HitlGateway\TaskAgentDrivers\DummyTaskAgent;
use App\Services\HitlGateway\TaskAgentManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HitlGatewayManagersTest extends TestCase
{
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
}
