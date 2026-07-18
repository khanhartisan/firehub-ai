<?php

namespace App\Services\HitlGateway\TaskAgentDrivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskAgent;
use App\Contracts\HitlGateway\TaskConclusion;
use App\Contracts\HitlGateway\TaskStatus;
use App\Models\File;

class DummyTaskAgent implements TaskAgent
{
    public function __construct(protected array $config = [])
    {
    }

    public function planTask(SemanticContext $context, array $files = []): Task
    {
        $title = $this->stringValue($context, ['title', 'name', 'subject']);
        if ($title === null) {
            $title = (string) ($this->config['default_title'] ?? 'Untitled task');
        }

        $description = $this->stringValue($context, ['description', 'request', 'payload', 'body']);
        if ($description === null) {
            $description = $this->contextToMarkdown($context);
        }

        $task = (new Task)
            ->setTitle($title)
            ->setDescription($description !== '' ? $description : null)
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

    public function conclude(Task $task): TaskConclusion
    {
        $text = $task->getOutput()?->getContent();
        if ($text === null || trim($text) === '') {
            $text = match ($task->getStatus()) {
                TaskStatus::APPROVED => 'Task approved.',
                TaskStatus::REJECTED => 'Task rejected.',
                TaskStatus::DOING => 'Task is in progress.',
                TaskStatus::PENDING => 'Task is pending.',
            };
        }

        $conclusion = (new TaskConclusion)->setConclusion($text);

        $files = $task->getOutput()?->getFiles() ?? [];
        if ($files !== []) {
            $conclusion->setFiles($files);
        }

        return $conclusion;
    }

    /**
     * @param  list<string>  $keys
     */
    protected function stringValue(SemanticContext $context, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! $context->has($key)) {
                continue;
            }

            $value = $context->getValue($key);
            if (is_string($value)) {
                $value = trim($value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    protected function contextToMarkdown(SemanticContext $context): string
    {
        $lines = [];

        foreach ($context->toArray() as $key => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $label = is_string($entry['description'] ?? null) && trim($entry['description']) !== ''
                ? trim($entry['description'])
                : (string) $key;
            $value = $entry['value'] ?? null;

            if (is_array($value)) {
                $value = implode(', ', array_map(static fn ($item) => (string) $item, $value));
            } elseif ($value === null) {
                $value = '';
            } else {
                $value = (string) $value;
            }

            $lines[] = '- **'.$label.'**: '.$value;
        }

        return implode("\n", $lines);
    }
}
