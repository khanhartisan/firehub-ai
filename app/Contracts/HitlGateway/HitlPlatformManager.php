<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\Configurable;
use App\Contracts\Contextable;

interface HitlPlatformManager extends Configurable, Contextable
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
     * @return bool
     */
    public function createTask(Task $task): bool;

    /**
     * This method will mutate the task if updated successfully
     *
     * @param string $reference
     * @param TaskAction $action
     * @return ?Task $task
     */
    public function updateTask(string $reference, TaskAction $action): ?Task;
}