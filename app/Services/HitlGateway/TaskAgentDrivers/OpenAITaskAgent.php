<?php

namespace App\Services\HitlGateway\TaskAgentDrivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\Role;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskAgent;
use App\Contracts\HitlGateway\TaskConclusion;
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

    public function planTask(SemanticContext $context, array $files = []): Task
    {
        if ($context->toArray() === []) {
            throw new RuntimeException('Task context cannot be empty.');
        }

        $prompt = $this->buildPlanTaskPrompt($context, $files);
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

        if (in_array($task->getStatus(), [TaskStatus::COMPLETED, TaskStatus::REJECTED], true)) {
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

    public function conclude(Task $task): TaskConclusion
    {
        $prompt = $this->buildConcludePrompt($task);
        $data = $this->requestStructuredJson(
            $prompt,
            'hitl_task_conclusion',
            $this->buildConcludeJsonSchema(),
            'Failed to conclude HITL task with OpenAI'
        );

        $conclusionText = array_key_exists('conclusion', $data) && $data['conclusion'] !== null
            ? (string) $data['conclusion']
            : null;

        // Model must not invent file IDs; keep only File instances already present on the task.
        return (new TaskConclusion)
            ->setResolved((bool) ($data['resolved'] ?? false))
            ->setConclusion($conclusionText)
            ->setFiles($this->resolveKnownTaskFiles($task, $data['files'] ?? []));
    }

    /**
     * @param  File[]  $files
     */
    protected function buildPlanTaskPrompt(SemanticContext $context, array $files): string
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
            'context' => $context->toArray(),
            'files' => $fileContext,
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

Turn the provided semantic context into a structured HITL task for a human worker.
Infer a concise title and a clear description from the context fields.
The description must be written in Markdown (headings, lists, emphasis, and links when useful).
Only set assignee/advisor/owner/followers when the context clearly identifies people.
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

    protected function buildConcludePrompt(Task $task): string
    {
        $taskJson = Json::encode($task->toArray());
        $knownFileIds = $this->knownTaskFileIds($task);
        $knownFileIdsJson = Json::encode($knownFileIds);
        $statusValues = implode(', ', array_map(
            static fn (TaskStatus $status): string => $status->value,
            TaskStatus::cases()
        ));

        return <<<PROMPT
You are a Human-in-the-Loop task agent.

Read the given task data and generate its current conclusion.
Summarize the outcome, key decisions, and any remaining open points based on status, messages, and output.
Write the conclusion in Markdown when useful.
If result files from the task (especially output.files) are relevant to the conclusion, include those file IDs in files.
Do not invent file IDs. Only use IDs from the known file ID list below.

Set resolved based on whether the human settled the task concern:
- resolved=false when the human's response is missing information or only partially answers the concern.
- resolved=true when the human fully answered the concern.
- resolved=true when there is a clear signal that the human insists the problem was answered (even if the answer seems incomplete).
- resolved=true when the human indicates they do not know how to answer or cannot provide the requested information.

Allowed status values (for interpreting the task): {$statusValues}
Known file IDs: {$knownFileIdsJson}

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
    protected function buildConcludeJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resolved' => [
                    'type' => 'boolean',
                    'description' => 'True when the human fully settled the concern (complete answer, insisted it was answered, or cannot answer); false when information is missing or only partial',
                ],
                'conclusion' => [
                    'type' => ['string', 'null'],
                    'description' => 'Markdown summary of the task\'s current conclusion',
                ],
                'files' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Relevant file IDs from the task/output; do not invent IDs',
                ],
            ],
            'required' => ['resolved', 'conclusion', 'files'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, File>
     */
    protected function knownTaskFilesById(Task $task): array
    {
        $byId = [];

        foreach ([$task->getFiles(), $task->getOutput()?->getFiles() ?? []] as $files) {
            foreach ($files as $file) {
                if (! $file instanceof File) {
                    continue;
                }

                $id = $file->getKey();
                if ($id !== null && $id !== '') {
                    $byId[(string) $id] = $file;
                }
            }
        }

        return $byId;
    }

    /**
     * @return list<string>
     */
    protected function knownTaskFileIds(Task $task): array
    {
        return array_keys($this->knownTaskFilesById($task));
    }

    /**
     * @param  mixed  $candidateIds
     * @return File[]
     */
    protected function resolveKnownTaskFiles(Task $task, mixed $candidateIds): array
    {
        if (! is_array($candidateIds)) {
            return [];
        }

        $known = $this->knownTaskFilesById($task);
        $files = [];
        $seen = [];

        foreach ($candidateIds as $id) {
            if (! is_string($id) && ! is_int($id)) {
                continue;
            }

            $id = (string) $id;
            if ($id === '' || isset($seen[$id]) || ! isset($known[$id])) {
                continue;
            }

            $seen[$id] = true;
            $files[] = $known[$id];
        }

        return $files;
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
