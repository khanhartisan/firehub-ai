<?php

namespace App\Contracts\PlatformManager;

use App\Contracts\Clonable;

interface PlatformManager extends Clonable
{
    public function setConfig(Config $config): static;

    public function getConfig(): ?Config;

    public function makeConfig(): ?Config;

    public function makeChannelConfig(): ?Config;
}
