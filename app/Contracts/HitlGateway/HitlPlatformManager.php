<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\CommonData\SemanticContext;

interface HitlPlatformManager
{
    /**
     * Fetch task data from the platform by reference
     *
     * @param string $reference
     * @return Task|null
     */
    public function fetchTask(string $reference): ?Task;

    /**
     * This method will mutate the Task if created successfully
     *
     * @param Task $task
     * @param SemanticContext|null $hitlPlatformContext
     * @return bool
     */
    public function createTask(Task $task, ?SemanticContext $hitlPlatformContext = null): bool;

    /**
     * This method will mutate the task if updated successfully
     *
     * @param Task $task
     * @param TaskAction $action
     * @param SemanticContext|null $hitlPlatformContext
     * @return bool
     */
    public function updateTask(Task $task, TaskAction $action, ?SemanticContext $hitlPlatformContext = null): bool;
}