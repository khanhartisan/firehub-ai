<?php

namespace App\Services\HitlGateway;

use App\Contracts\HitlGateway\HitlGateway;
use App\Contracts\HitlGateway\HitlPlatformManager;
use App\Contracts\HitlGateway\TaskAgent;

abstract class HitlGatewayService implements HitlGateway
{
    protected HitlPlatformManager $hitlPlatformManager;

    protected TaskAgent $taskAgent;

    public function setHitlPlatformManager(HitlPlatformManager $hitlPlatformManager): static
    {
        $this->hitlPlatformManager = $hitlPlatformManager;

        return $this;
    }

    public function getHitlPlatformManager(): HitlPlatformManager
    {
        return $this->hitlPlatformManager;
    }

    public function setTaskAgent(TaskAgent $taskAgent): static
    {
        $this->taskAgent = $taskAgent;

        return $this;
    }

    public function getTaskAgent(): TaskAgent
    {
        return $this->taskAgent;
    }
}
