<?php

namespace App\Services\HitlGateway\Drivers\Dummy;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\HitlPlatformManager;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use Illuminate\Support\Str;

class DummyHitlPlatformManager implements HitlPlatformManager
{
    /** @var array<string, array<string, mixed>> */
    protected array $tasks = [];

    public function __construct(protected array $config = [])
    {
    }

    public function fetchTask(string $reference): ?Task
    {
        if (! isset($this->tasks[$reference])) {
            return null;
        }

        return Task::fromArray($this->tasks[$reference]);
    }

    public function createTask(Task $task, ?SemanticContext $hitlPlatformContext = null): bool
    {
        if ($hitlPlatformContext !== null) {
            $task->setContext($hitlPlatformContext);
        }

        $reference = trim((string) $task->getReference());
        if ($reference === '') {
            $prefix = (string) ($this->config['reference_prefix'] ?? 'dummy');
            $reference = $prefix.'-'.Str::uuid()->toString();
            $task->setReference($reference);
        }

        if (isset($this->tasks[$reference])) {
            return false;
        }

        $this->tasks[$reference] = $task->toArray();

        return true;
    }

    public function updateTask(Task $task, TaskAction $action, ?SemanticContext $hitlPlatformContext = null): bool
    {
        $reference = trim((string) $task->getReference());
        if ($reference === '' || ! isset($this->tasks[$reference])) {
            return false;
        }

        $stored = Task::fromArray($this->tasks[$reference]);

        if ($hitlPlatformContext !== null) {
            $stored->setContext($hitlPlatformContext);
            $task->setContext($hitlPlatformContext);
        }

        if ($action->getStatus() !== null) {
            $stored->setStatus($action->getStatus());
            $task->setStatus($action->getStatus());
        }

        if ($action->getMessage() !== null) {
            $stored->addMessage($action->getMessage());
            $task->addMessage($action->getMessage());
        }

        if ($action->getOutput() !== null) {
            $stored->setOutput($action->getOutput());
            $task->setOutput($action->getOutput());
        }

        $this->tasks[$reference] = $stored->toArray();

        return true;
    }
}
