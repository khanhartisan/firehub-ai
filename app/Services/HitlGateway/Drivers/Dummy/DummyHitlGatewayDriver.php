<?php

namespace App\Services\HitlGateway\Drivers\Dummy;

use App\Services\HitlGateway\HitlGatewayService;

class DummyHitlGatewayDriver extends HitlGatewayService
{
    public function __construct(array $config = [])
    {
        $platformConfig = $config['platform'] ?? $config;
        $agentConfig = $config['agent'] ?? $config;

        $this->setHitlPlatformManager(new DummyHitlPlatformManager($platformConfig));
        $this->setTaskAgent(new DummyTaskAgent($agentConfig));
    }
}
