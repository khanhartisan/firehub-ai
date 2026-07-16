<?php

namespace App\Facades\HitlGateway;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\HitlPlatformManager as HitlPlatformManagerContract;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use Illuminate\Support\Facades\Facade;

/**
 * @method static HitlPlatformManagerContract driver(string|null $driver = null)
 * @method static Task|null fetchTask(string $reference)
 * @method static bool createTask(Task $task, ?SemanticContext $hitlPlatformContext = null)
 * @method static bool updateTask(Task $task, TaskAction $action, ?SemanticContext $hitlPlatformContext = null)
 *
 * @see \App\Services\HitlGateway\HitlPlatformManagerManager
 */
class HitlPlatformManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'hitl_platform_manager.manager';
    }
}
