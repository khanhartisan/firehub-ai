<?php

namespace App\Contracts\PlatformManager;

interface PlatformManager
{
    public function setConfig(Config $config): static;

    public function getConfig(): ?Config;

    public function makeChannelConfig(): ?Config;
}
