<?php

namespace App\Services\HitlGateway\HitlPlatformManagerDrivers;

use App\Concerns\Contextable;
use App\Contracts\Config;
use App\Contracts\HitlGateway\HitlPlatformConfig;
use App\Contracts\HitlGateway\HitlPlatformManager;

abstract class AbstractHitlPlatformManager implements HitlPlatformManager
{
    use Contextable;

    protected HitlPlatformConfig $platformConfig;

    public function setConfig(Config $config): static
    {
        if (!$config instanceof HitlPlatformConfig) {
            throw new \InvalidArgumentException('config must be an instance of HitlPlatformConfig');
        }

        $this->platformConfig = $config;
        return $this;
    }

    public function getConfig(): ?Config
    {
        return $this->platformConfig ?? null;
    }

    public function makeConfig(): ?Config
    {
        return new HitlPlatformConfig();
    }
}