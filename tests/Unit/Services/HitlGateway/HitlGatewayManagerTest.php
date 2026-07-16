<?php

namespace Tests\Unit\Services\HitlGateway;

use App\Facades\HitlGateway;
use App\Services\HitlGateway\Drivers\Dummy\DummyHitlGatewayDriver;
use App\Services\HitlGateway\HitlGatewayManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HitlGatewayManagerTest extends TestCase
{
    public function test_it_returns_default_driver(): void
    {
        Config::set('hitlgateway.default', 'dummy');

        $manager = HitlGateway::getFacadeRoot();

        $driver = $manager->driver();

        $this->assertInstanceOf(DummyHitlGatewayDriver::class, $driver);
    }

    public function test_get_default_driver_returns_configured_value(): void
    {
        Config::set('hitlgateway.default', 'dummy');

        $manager = HitlGateway::getFacadeRoot();

        $this->assertSame('dummy', $manager->getDefaultDriver());
    }

    public function test_facade_returns_hitl_gateway_manager_instance(): void
    {
        $manager = HitlGateway::getFacadeRoot();

        $this->assertInstanceOf(HitlGatewayManager::class, $manager);
    }
}
