<?php

namespace App\Services\Platforms\FlyCms;

use App\Contracts\Platforms\FlyCms\Config;
use App\Contracts\Platforms\FlyCms\FlyCms;

abstract class FlyCmsService implements FlyCms
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
}
