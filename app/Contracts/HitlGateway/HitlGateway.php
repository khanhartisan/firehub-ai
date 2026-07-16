<?php

namespace App\Contracts\HitlGateway;

interface HitlGateway
{
    public function setHitlPlatformManager(HitlPlatformManager $hitlPlatformManager): static;

    public function getHitlPlatformManager(): HitlPlatformManager;

    public function setTaskAgent(TaskAgent $taskAgent): static;

    public function getTaskAgent(): TaskAgent;
}