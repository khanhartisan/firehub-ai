<?php

namespace App\Contracts\HitlGateway;

use App\Models\File;

interface TaskAgent
{
    /**
     * Planning a task
     *
     * @param string $payload
     * @param File[] $files
     * @return Task
     */
    public function planTask(string $payload, array $files = []): Task;

    /**
     * Plan a task action if needed
     *
     * @param Task $task
     * @return TaskAction|null
     */
    public function action(Task $task): ?TaskAction;
}