<?php

namespace App\Services\HitlGateway\HitlPlatformManagerDrivers;

use App\Contracts\HitlGateway\HitlPlatformManager;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use Illuminate\Support\Str;

class DummyHitlPlatformManager extends AbstractHitlPlatformManager implements HitlPlatformManager
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

    public function createTask(Task $task): bool
    {
        if ($context = $this->getContext()) {
            $task->setContext($context);
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

    public function updateTask(string $reference, TaskAction $action): ?Task
    {
        $reference = trim($reference);
        if ($reference === '' || ! isset($this->tasks[$reference])) {
            return null;
        }

        $stored = Task::fromArray($this->tasks[$reference]);

        if ($context = $this->getContext()) {
            $stored->setContext($context);
        }

        if ($action->getStatus() !== null) {
            $stored->setStatus($action->getStatus());
        }

        if ($action->getMessage() !== null) {
            $stored->addMessage($action->getMessage());
        }

        if ($action->getOutput() !== null) {
            $stored->setOutput($action->getOutput());
        }

        $this->tasks[$reference] = $stored->toArray();

        return $stored;
    }
}
