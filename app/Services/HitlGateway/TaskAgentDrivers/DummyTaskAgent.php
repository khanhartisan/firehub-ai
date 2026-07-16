<?php

namespace App\Services\HitlGateway\TaskAgentDrivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskAgent;
use App\Contracts\HitlGateway\TaskStatus;
use App\Models\File;

class DummyTaskAgent implements TaskAgent
{
    public function __construct(protected array $config = [])
    {
    }

    public function planTask(string $payload, array $files = [], ?SemanticContext $context = null): Task
    {
        $payload = trim($payload);
        $lines = preg_split('/\R+/', $payload) ?: [];
        $title = trim($lines[0] ?? '');
        if ($title === '') {
            $title = (string) ($this->config['default_title'] ?? 'Untitled task');
        }

        $task = (new Task)
            ->setTitle($title)
            ->setDescription($payload !== '' ? $payload : null)
            ->setStatus(TaskStatus::PENDING)
            ->setContext($context);

        if ($files !== []) {
            $task->setFiles(array_values(array_filter(
                $files,
                static fn ($file): bool => $file instanceof File
            )));
        }

        return $task;
    }

    public function action(Task $task): ?TaskAction
    {
        if (! ($this->config['auto_action'] ?? true)) {
            return null;
        }

        return match ($task->getStatus()) {
            TaskStatus::PENDING => (new TaskAction)->setStatus(TaskStatus::DOING),
            default => null,
        };
    }
}
