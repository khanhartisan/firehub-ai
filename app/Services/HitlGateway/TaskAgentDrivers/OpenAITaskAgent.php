<?php

namespace App\Services\HitlGateway\TaskAgentDrivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\Role;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskAgent;
use App\Contracts\HitlGateway\TaskStatus;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Models\File;
use App\Utils\Json;
use RuntimeException;

class OpenAITaskAgent implements TaskAgent
{
    protected OpenAIClient $openAIClient;

    protected array $config;

    public function __construct(OpenAIClient $openAIClient, array $config = [])
    {
        $this->openAIClient = $openAIClient;
        $this->config = $config;
    }

    public function planTask(string $payload, array $files = [], ?SemanticContext $context = null): Task
    {
        $payload = trim($payload);
        if ($payload === '') {
            throw new RuntimeException('Task payload cannot be empty.');
        }

        $prompt = $this->buildPlanTaskPrompt($payload, $files, $context);
        $data = $this->requestStructuredJson(
            $prompt,
            'hitl_plan_task',
            $this->buildPlanTaskJsonSchema(),
            'Failed to plan HITL task with OpenAI'
        );

        try {
            $task = Task::fromArray([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => TaskStatus::PENDING->value,
                'assignee' => $data['assignee'] ?? null,
                'advisor' => $data['advisor'] ?? null,
                'owner' => $data['owner'] ?? null,
                'followers' => $data['followers'] ?? [],
                'messages' => $data['messages'] ?? [],
                'output' => null,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw new RuntimeException('Invalid planned task payload: '.$e->getMessage(), 0, $e);
        }

        $title = trim((string) $task->getTitle());
        if ($title === '') {
            $task->setTitle((string) ($this->config['default_title'] ?? 'Untitled task'));
        }

        $task->setStatus(TaskStatus::PENDING);
        $task->setContext($context);
        $task->setFiles(array_values(array_filter(
            $files,
            static fn ($file): bool => $file instanceof File
        )));

        return $task;
    }

    public function action(Task $task): ?TaskAction
    {
        if (! ($this->config['auto_action'] ?? true)) {
            return null;
        }

        if (in_array($task->getStatus(), [TaskStatus::APPROVED, TaskStatus::REJECTED], true)) {
            return null;
        }

        $prompt = $this->buildActionPrompt($task);
        $data = $this->requestStructuredJson(
            $prompt,
            'hitl_task_action',
            $this->buildActionJsonSchema(),
            'Failed to plan HITL task action with OpenAI'
        );

        if (empty($data['should_act'])) {
            return null;
        }

        $actionData = $data['action'] ?? null;
        if (! is_array($actionData)) {
            return null;
        }

        // Model must not invent file IDs; keep output files empty and attach only content.
        if (isset($actionData['output']) && is_array($actionData['output'])) {
            $actionData['output']['files'] = [];
        }

        try {
            return TaskAction::fromArray($actionData);
        } catch (\InvalidArgumentException $e) {
            throw new RuntimeException('Invalid task action payload: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  File[]  $files
     */
    protected function buildPlanTaskPrompt(string $payload, array $files, ?SemanticContext $context = null): string
    {
        $fileContext = array_map(static function (File $file): array {
            return [
                'id' => $file->getKey(),
                'url' => $file->url ?? null,
                'mime_type' => $file->mime_type ?? null,
                'extension' => $file->extension ?? null,
                'description' => $file->description ?? null,
                'size' => $file->size ?? null,
            ];
        }, array_values(array_filter(
            $files,
            static fn ($file): bool => $file instanceof File
        )));

        $inputJson = Json::encode([
            'payload' => $payload,
            'files' => $fileContext,
            'context' => $context?->toArray(),
        ]);

        $statusValues = implode(', ', array_map(
            static fn (TaskStatus $status): string => $status->value,
            TaskStatus::cases()
        ));
        $roleValues = implode(', ', array_map(
            static fn (Role $role): string => $role->value,
            Role::cases()
        ));

        return <<<PROMPT
You are a Human-in-the-Loop task planner.

Turn the free-form request into a structured HITL task for a human worker.
Infer a concise title and a clear description.
The description must be written in Markdown (headings, lists, emphasis, and links when useful).
Use the provided semantic context when available to ground the task.
Only set assignee/advisor/owner/followers when the request clearly identifies people.
Do not invent file IDs. Attached files are provided for context only.
The planned task will always start as pending.

Allowed status values: {$statusValues}
Allowed human roles: {$roleValues}

Input:
{$inputJson}
PROMPT;
    }

    protected function buildActionPrompt(Task $task): string
    {
        $taskJson = Json::encode($task->toArray());
        $statusValues = implode(', ', array_map(
            static fn (TaskStatus $status): string => $status->value,
            TaskStatus::cases()
        ));

        return <<<PROMPT
You are a Human-in-the-Loop task agent.

Given the current task state, decide whether an automated action is needed now.
Return should_act=false when the task should wait for a human or is already complete.

Guidance:
- If status is pending, usually move to doing and optionally leave a short kickoff message.
- If status is doing, only act when there is enough information to approve/reject or provide output; otherwise should_act=false.
- If status is approved or rejected, should_act must be false.
- Do not invent file IDs in output.files; leave files empty.

Allowed status values: {$statusValues}

Current task:
{$taskJson}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPlanTaskJsonSchema(): array
    {
        $human = $this->humanJsonSchemaObject();
        $message = $this->messageJsonSchemaObject();

        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Short task title',
                ],
                'description' => [
                    'type' => ['string', 'null'],
                    'description' => 'Full task description for the human worker, written in Markdown',
                ],
                'assignee' => [
                    'anyOf' => [
                        ['type' => 'null'],
                        $human,
                    ],
                ],
                'advisor' => [
                    'anyOf' => [
                        ['type' => 'null'],
                        $human,
                    ],
                ],
                'owner' => [
                    'anyOf' => [
                        ['type' => 'null'],
                        $human,
                    ],
                ],
                'followers' => [
                    'type' => 'array',
                    'items' => $human,
                ],
                'messages' => [
                    'type' => 'array',
                    'items' => $message,
                ],
            ],
            'required' => [
                'title',
                'description',
                'assignee',
                'advisor',
                'owner',
                'followers',
                'messages',
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildActionJsonSchema(): array
    {
        $action = [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'anyOf' => [
                        ['type' => 'null'],
                        [
                            'type' => 'string',
                            'enum' => array_map(
                                static fn (TaskStatus $status): string => $status->value,
                                TaskStatus::cases()
                            ),
                        ],
                    ],
                ],
                'message' => [
                    'anyOf' => [
                        ['type' => 'null'],
                        $this->messageJsonSchemaObject(),
                    ],
                ],
                'output' => [
                    'anyOf' => [
                        ['type' => 'null'],
                        [
                            'type' => 'object',
                            'properties' => [
                                'content' => [
                                    'type' => ['string', 'null'],
                                ],
                                'files' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                            'required' => ['content', 'files'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ],
            'required' => ['status', 'message', 'output'],
            'additionalProperties' => false,
        ];

        return [
            'type' => 'object',
            'properties' => [
                'should_act' => [
                    'type' => 'boolean',
                    'description' => 'True when an automated task action should be applied now',
                ],
                'action' => [
                    'anyOf' => [
                        ['type' => 'null'],
                        $action,
                    ],
                ],
            ],
            'required' => ['should_act', 'action'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function humanJsonSchemaObject(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'role' => [
                    'type' => 'string',
                    'enum' => array_map(
                        static fn (Role $role): string => $role->value,
                        Role::cases()
                    ),
                ],
                'email' => [
                    'type' => ['string', 'null'],
                ],
                'name' => [
                    'type' => ['string', 'null'],
                ],
                'description' => [
                    'type' => ['string', 'null'],
                ],
            ],
            'required' => ['role', 'email', 'name', 'description'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageJsonSchemaObject(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'human' => [
                    'anyOf' => [
                        ['type' => 'null'],
                        $this->humanJsonSchemaObject(),
                    ],
                ],
                'message' => [
                    'type' => ['string', 'null'],
                ],
                'datetime' => [
                    'type' => ['string', 'null'],
                    'description' => 'ISO-8601 datetime or null',
                ],
            ],
            'required' => ['human', 'message', 'datetime'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestStructuredJson(
        string $prompt,
        string $schemaName,
        array $jsonSchema,
        string $errorPrefix
    ): array {
        $input = ResponseInput::text($prompt);
        $options = ResponseOptions::create()
            ->model($this->getModel())
            ->temperature($this->getTemperature())
            ->responseFormat([
                'type' => 'json_schema',
                'name' => $schemaName,
                'schema' => $jsonSchema,
                'strict' => true,
            ]);

        try {
            $response = $this->openAIClient->createResponse($input, $options);
        } catch (\Exception $e) {
            throw new RuntimeException("{$errorPrefix}: {$e->getMessage()}", 0, $e);
        }

        $this->checkForRefusal($response);

        $responseText = $response->getFirstOutputText();
        if ($responseText === null || $responseText === '') {
            throw new RuntimeException("{$errorPrefix}: empty response");
        }

        try {
            $data = Json::decode($responseText, true);
        } catch (\UnexpectedValueException $e) {
            throw new RuntimeException("{$errorPrefix}: invalid JSON response", 0, $e);
        }

        if (! is_array($data)) {
            throw new RuntimeException("{$errorPrefix}: JSON did not decode to an object");
        }

        return $data;
    }

    protected function checkForRefusal(Response $response): void
    {
        foreach ($response->getOutput() as $item) {
            if (($item['type'] ?? null) !== 'message' || ! isset($item['content'])) {
                continue;
            }

            foreach ($item['content'] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    $refusalMessage = $content['refusal'] ?? 'The model refused to complete this request.';
                    throw new RuntimeException("OpenAI refused the request: {$refusalMessage}");
                }
            }
        }
    }

    protected function getModel(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    protected function getTemperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.2);
    }
}
