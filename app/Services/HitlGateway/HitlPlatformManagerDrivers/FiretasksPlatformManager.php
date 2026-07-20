<?php

namespace App\Services\HitlGateway\HitlPlatformManagerDrivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Config;
use App\Contracts\HitlGateway\HitlPlatformManager;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;

class FiretasksPlatformManager extends AbstractHitlPlatformManager implements HitlPlatformManager
{

    public function fetchTask(string $reference): ?Task
    {
        // TODO: Implement fetchTask() method.
        return null;
    }

    public function createTask(Task $task, ?SemanticContext $hitlPlatformContext = null): bool
    {
        // TODO: Implement createTask() method.
        return false;
    }

    public function updateTask(Task $task, TaskAction $action, ?SemanticContext $hitlPlatformContext = null): bool
    {
        // TODO: Implement updateTask() method.
        return false;
    }

    public function makeConfig(): ?Config
    {
        return new FiretasksPlatformManager\Config();
    }
}