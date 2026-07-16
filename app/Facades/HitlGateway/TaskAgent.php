<?php

namespace App\Facades\HitlGateway;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskAgent as TaskAgentContract;
use Illuminate\Support\Facades\Facade;

/**
 * @method static TaskAgentContract driver(string|null $driver = null)
 * @method static Task planTask(string $payload, array $files = [], ?SemanticContext $context = null)
 * @method static TaskAction|null action(Task $task)
 *
 * @see \App\Services\HitlGateway\TaskAgentManager
 */
class TaskAgent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'hitl_task_agent.manager';
    }
}
