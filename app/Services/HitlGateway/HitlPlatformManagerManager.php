<?php

namespace App\Services\HitlGateway;

use App\Contracts\HitlGateway\HitlPlatformManager;
use Illuminate\Support\Manager;

class HitlPlatformManagerManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('hitlgateway.platform_manager', 'dummy');
    }

    protected function createDummyDriver(): HitlPlatformManager
    {
        $config = $this->config->get('hitlgateway.platform_manager_drivers.dummy', []);

        return new HitlPlatformManagerDrivers\DummyHitlPlatformManager($config);
    }
}
