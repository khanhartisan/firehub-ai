<?php

namespace App\Services\PlatformManager\FlyCms;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Contracts\PlatformManager\FlyCms\FlyCms;
use Illuminate\Support\Manager;

class FlyCmsManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('flycms.default', 'pseudo');
    }

    protected function createPseudoDriver(): FlyCms
    {
        $config = $this->config->get('flycms.drivers.pseudo', []);

        return (new Drivers\PseudoFlyCmsDriver)
            ->setConfig(new Config($config));
    }
}
