<?php

namespace App\Services\Platforms\FlyCms;

use App\Contracts\Platforms\FlyCms\Config;
use App\Contracts\Platforms\FlyCms\FlyCms;
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
