<?php

namespace App\Contracts\PlatformManager;

use App\Contracts\Clonable;
use App\Contracts\Config;
use App\Contracts\Configurable;

interface PlatformManager extends Clonable, Configurable
{
    public function makeChannelConfig(): ?Config;
}
