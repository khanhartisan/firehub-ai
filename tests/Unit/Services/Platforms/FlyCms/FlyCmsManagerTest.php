<?php

namespace Tests\Unit\Services\Platforms\FlyCms;

use App\Facades\Platforms\FlyCms;
use App\Services\Platforms\FlyCms\Drivers\PseudoFlyCmsDriver;
use App\Services\Platforms\FlyCms\FlyCmsManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FlyCmsManagerTest extends TestCase
{
    public function test_it_returns_default_pseudo_driver(): void
    {
        Config::set('flycms.default', 'pseudo');
        Config::set('flycms.drivers.pseudo', [
            'base_url' => 'https://flycms.test',
            'api_key' => 'pseudo-api-key',
        ]);

        $manager = FlyCms::getFacadeRoot();
        $driver = $manager->driver();

        $this->assertInstanceOf(PseudoFlyCmsDriver::class, $driver);
        $this->assertSame('https://flycms.test', $driver->getConfig()?->getBaseUrl());
        $this->assertSame('pseudo-api-key', $driver->getConfig()?->getApiKey());
    }

    public function test_get_default_driver_returns_configured_value(): void
    {
        Config::set('flycms.default', 'pseudo');

        $manager = FlyCms::getFacadeRoot();

        $this->assertSame('pseudo', $manager->getDefaultDriver());
    }

    public function test_facade_returns_flycms_manager_instance(): void
    {
        $manager = FlyCms::getFacadeRoot();

        $this->assertInstanceOf(FlyCmsManager::class, $manager);
    }
}
