<?php

namespace App\Contracts\HitlGateway;

use App\Contracts\CommonData\SemanticContext;
use App\Models\File;

interface TaskAgent
{
    /**
     * Planning a task
     *
     * @param SemanticContext $context
     * @param File[] $files
     * @return Task
     */
    public function planTask(SemanticContext $context, array $files = []): Task;

    /**
     * Plan a task action if needed
     *
     * @param Task $task
     * @return TaskAction|null
     */
    public function action(Task $task): ?TaskAction;

    /**
     * Read the given task data and generate its current conclusion
     *
     * @param Task $task
     * @return TaskConclusion
     */
    public function conclude(Task $task): TaskConclusion;
}