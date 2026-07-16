<?php

namespace App\Services\HitlGateway;

use App\Contracts\HitlGateway\HitlGateway;
use Illuminate\Support\Manager;

class HitlGatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('hitlgateway.default', 'dummy');
    }

    protected function createDummyDriver(): HitlGateway
    {
        $config = $this->config->get('hitlgateway.drivers.dummy', []);

        return new Drivers\Dummy\DummyHitlGatewayDriver($config);
    }
}
