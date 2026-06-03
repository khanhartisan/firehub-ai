<?php

namespace App\Services\PlatformManager\FlyCms;

use App\Contracts\PlatformManager\Config;
use App\Contracts\PlatformManager\FlyCms\FlyCms;
use App\Services\PlatformManager\PlatformManager;

abstract class FlyCmsService extends PlatformManager implements FlyCms
{
    public function setConfig(Config $config): static
    {
        if (!$config instanceof \App\Contracts\PlatformManager\FlyCms\Config) {
            throw new \Exception('Invalid FlyCms config');
        }

        return parent::setConfig($config);
    }

    public function makeConfig(): ?Config
    {
        return new \App\Contracts\PlatformManager\FlyCms\Config();
    }
}
