<?php

namespace App\Facades;

use App\Contracts\HitlGateway\HitlGateway as HitlGatewayContract;
use App\Contracts\HitlGateway\HitlPlatformManager;
use App\Contracts\HitlGateway\TaskAgent;
use Illuminate\Support\Facades\Facade;

/**
 * @method static HitlGatewayContract driver(string|null $driver = null)
 * @method static HitlGatewayContract setHitlPlatformManager(HitlPlatformManager $hitlPlatformManager)
 * @method static HitlPlatformManager getHitlPlatformManager()
 * @method static HitlGatewayContract setTaskAgent(TaskAgent $taskAgent)
 * @method static TaskAgent getTaskAgent()
 *
 * @see \App\Services\HitlGateway\HitlGatewayManager
 */
class HitlGateway extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'hitl_gateway.manager';
    }
}
