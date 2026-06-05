<?php

namespace App\Services\PlatformManager;

use App\Contracts\Clonable;
use App\Contracts\PlatformManager\Config;

abstract class PlatformManager implements \App\Contracts\PlatformManager\PlatformManager
{
    protected ?Config $config = null;

    public function setConfig(Config $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function getConfig(): ?Config
    {
        return $this->config;
    }

    public function makeConfig(): ?Config
    {
        return null;
    }

    public function makeChannelConfig(): ?Config
    {
        return null;
    }

    public function clone(): \App\Contracts\Clonable
    {
        $platformManager = new static();

        /** @var Config $config */
        if ($config = $this->getConfig()?->clone()) {
            $platformManager->setConfig($config);
        }

        return $platformManager;
    }
}
