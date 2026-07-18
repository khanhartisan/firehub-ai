<?php

namespace App\Console\Commands\LiveTests;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\Human;
use App\Contracts\HitlGateway\Message;
use App\Contracts\HitlGateway\Role;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskConclusion;
use App\Contracts\HitlGateway\TaskOutput;
use App\Contracts\HitlGateway\TaskStatus;
use App\Facades\HitlGateway\HitlPlatformManager;
use App\Facades\HitlGateway\TaskAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class TestHitlGatewayService extends Command
{
    protected $signature = 'live-test:test-hitl-gateway-service
                            {--task-agent= : TaskAgent driver (dummy, openai, openai_compatible)}
                            {--platform-manager= : HitlPlatformManager driver (dummy)}
                            {--operation= : Operation: planTask|action|conclude|createTask|fetchTask|updateTask|full_flow}
                            {--context=* : Context key=value entries (repeat option for multiple)}
                            {--reference= : Task reference for fetchTask / updateTask}';

    protected $description = 'Run HitlGateway live tests for TaskAgent and/or HitlPlatformManager.';

    /** @var list<string> */
    private const OPERATIONS = [
        'planTask',
        'action',
        'conclude',
        'createTask',
        'fetchTask',
        'updateTask',
        'full_flow',
    ];

    public function handle(): int
    {
        $taskAgentDriver = $this->resolveTaskAgentDriver();
        if ($taskAgentDriver === null) {
            return self::FAILURE;
        }

        $platformDriver = $this->resolvePlatformManagerDriver();
        if ($platformDriver === null) {
            return self::FAILURE;
        }

        $operation = $this->resolveOperation();
        if ($operation === null) {
            return self::FAILURE;
        }

        $taskAgent = TaskAgent::driver($taskAgentDriver);
        $platform = HitlPlatformManager::driver($platformDriver);

        $this->newLine();
        $this->info("TaskAgent driver: {$taskAgentDriver}");
        $this->info("PlatformManager driver: {$platformDriver}");
        $this->info("Operation: {$operation}");
        $this->line('-----');

        try {
            return match ($operation) {
                'planTask' => $this->runPlanTask($taskAgent),
                'action' => $this->runAction($taskAgent),
                'conclude' => $this->runConclude($taskAgent),
                'createTask' => $this->runCreateTask($taskAgent, $platform),
                'fetchTask' => $this->runFetchTask($taskAgent, $platform),
                'updateTask' => $this->runUpdateTask($taskAgent, $platform),
                'full_flow' => $this->runFullFlow($taskAgent, $platform),
                default => self::FAILURE,
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    private function resolveTaskAgentDriver(): ?string
    {
        $drivers = array_keys(config('hitlgateway.task_agent_drivers', []));
        if ($drivers === []) {
            $this->error('No hitlgateway.task_agent_drivers configured.');

            return null;
        }

        $driver = (string) ($this->option('task-agent') ?: config('hitlgateway.task_agent', 'dummy'));

        if (! in_array($driver, $drivers, true)) {
            $this->error('Unknown task-agent driver "'.$driver.'". Available: '.implode(', ', $drivers));

            return null;
        }

        if (! $this->option('task-agent') && $this->input->isInteractive()) {
            $defaultIndex = array_search(config('hitlgateway.task_agent', 'dummy'), $drivers, true);

            return $this->choice(
                'Select TaskAgent driver',
                $drivers,
                $defaultIndex === false ? 0 : $defaultIndex
            );
        }

        return $driver;
    }

    private function resolvePlatformManagerDriver(): ?string
    {
        $drivers = array_keys(config('hitlgateway.platform_manager_drivers', []));
        if ($drivers === []) {
            $this->error('No hitlgateway.platform_manager_drivers configured.');

            return null;
        }

        $driver = (string) ($this->option('platform-manager') ?: config('hitlgateway.platform_manager', 'dummy'));

        if (! in_array($driver, $drivers, true)) {
            $this->error('Unknown platform-manager driver "'.$driver.'". Available: '.implode(', ', $drivers));

            return null;
        }

        if (! $this->option('platform-manager') && $this->input->isInteractive()) {
            $defaultIndex = array_search(config('hitlgateway.platform_manager', 'dummy'), $drivers, true);

            return $this->choice(
                'Select HitlPlatformManager driver',
                $drivers,
                $defaultIndex === false ? 0 : $defaultIndex
            );
        }

        return $driver;
    }

    private function resolveOperation(): ?string
    {
        $operations = self::OPERATIONS;
        $operation = (string) ($this->option('operation') ?? '');

        if ($operation !== '' && ! in_array($operation, $operations, true)) {
            $this->error('Unknown operation "'.$operation.'". Available: '.implode(', ', $operations));

            return null;
        }

        if ($operation === '') {
            if ($this->input->isInteractive()) {
                return $this->choice('Select operation to test', $operations, 6);
            }

            return 'full_flow';
        }

        return $operation;
    }

    private function runPlanTask(mixed $taskAgent): int
    {
        $context = $this->buildContext();
        $this->displayContextSummary($context);

        $task = $this->timedCall('planTask', fn () => $taskAgent->planTask($context));
        $this->displayTask($task, 'Planned task');

        return self::SUCCESS;
    }

    private function runAction(mixed $taskAgent): int
    {
        $task = $this->resolveTaskForAction($taskAgent);
        $this->displayTask($task, 'Input task');

        $action = $this->timedCall('action', fn () => $taskAgent->action($task));
        $this->displayAction($action);

        return self::SUCCESS;
    }

    private function runConclude(mixed $taskAgent): int
    {
        $task = $this->resolveTaskForAction($taskAgent);
        $this->displayTask($task, 'Input task');

        $conclusion = $this->timedCall('conclude', fn () => $taskAgent->conclude($task));
        $this->displayConclusion($conclusion);

        return self::SUCCESS;
    }

    private function runCreateTask(mixed $taskAgent, mixed $platform): int
    {
        $task = $this->resolveTaskForPlatform($taskAgent);
        $this->displayTask($task, 'Task to create');

        $created = $this->timedCall(
            'createTask',
            fn () => $platform->createTask($task)
        );

        $this->info('createTask() => '.($created ? 'true' : 'false'));
        $this->displayTask($task, 'Task after create');

        return $created ? self::SUCCESS : self::FAILURE;
    }

    private function runFetchTask(mixed $taskAgent, mixed $platform): int
    {
        $reference = $this->resolveReference($taskAgent, $platform);
        if ($reference === null) {
            return self::FAILURE;
        }

        $task = $this->timedCall('fetchTask', fn () => $platform->fetchTask($reference));
        if ($task === null) {
            $this->warn("No task found for reference [{$reference}].");

            return self::FAILURE;
        }

        $this->displayTask($task, 'Fetched task');

        return self::SUCCESS;
    }

    private function runUpdateTask(mixed $taskAgent, mixed $platform): int
    {
        $task = $this->resolveExistingPlatformTask($taskAgent, $platform);
        if ($task === null) {
            return self::FAILURE;
        }

        $this->displayTask($task, 'Task before update');

        $action = $this->resolveTaskAction($taskAgent, $task);
        $this->displayAction($action);

        if ($action === null) {
            $this->warn('No action to apply.');

            return self::FAILURE;
        }

        $updated = $this->timedCall(
            'updateTask',
            fn () => $platform->updateTask($task, $action)
        );

        $this->info('updateTask() => '.($updated ? 'true' : 'false'));
        $this->displayTask($task, 'Task after update');

        return $updated ? self::SUCCESS : self::FAILURE;
    }

    private function runFullFlow(mixed $taskAgent, mixed $platform): int
    {
        $context = $this->buildContext();
        $this->displayContextSummary($context);

        $task = $this->timedCall('planTask', fn () => $taskAgent->planTask($context));
        $this->displayTask($task, '1. Planned task');

        $created = $this->timedCall('createTask', fn () => $platform->createTask($task));
        $this->info('2. createTask() => '.($created ? 'true' : 'false'));
        if (! $created) {
            return self::FAILURE;
        }
        $this->displayTask($task, 'Task after create');

        $action = $this->timedCall('action', fn () => $taskAgent->action($task));
        $this->displayAction($action, '3. Agent action');

        if ($action !== null) {
            $updated = $this->timedCall(
                'updateTask',
                fn () => $platform->updateTask($task, $action)
            );
            $this->info('4. updateTask() => '.($updated ? 'true' : 'false'));
            if (! $updated) {
                return self::FAILURE;
            }
            $this->displayTask($task, 'Task after update');
        } else {
            $this->warn('4. Skipped updateTask (agent returned no action).');
        }

        $reference = (string) $task->getReference();
        $fetched = $this->timedCall('fetchTask', fn () => $platform->fetchTask($reference));
        if ($fetched === null) {
            $this->error("5. fetchTask({$reference}) returned null.");

            return self::FAILURE;
        }

        $this->displayTask($fetched, '5. Fetched task');

        return self::SUCCESS;
    }

    private function resolveTaskForAction(mixed $taskAgent): Task
    {
        if ($this->input->isInteractive()) {
            $mode = $this->choice(
                'How should the input task be obtained?',
                [
                    'Plan a new task via TaskAgent::planTask()',
                    'Use a local fixture task (pending review)',
                ],
                0
            );

            if (str_starts_with($mode, 'Plan')) {
                $context = $this->buildContext();
                $this->displayContextSummary($context);

                return $this->timedCall('planTask', fn () => $taskAgent->planTask($context));
            }
        }

        return $this->buildFixtureTask();
    }

    private function resolveTaskForPlatform(mixed $taskAgent): Task
    {
        if ($this->input->isInteractive()) {
            $mode = $this->choice(
                'How should the task be obtained?',
                [
                    'Plan a new task via TaskAgent::planTask()',
                    'Use a local fixture task',
                ],
                0
            );

            if (str_starts_with($mode, 'Plan')) {
                $context = $this->buildContext();
                $this->displayContextSummary($context);

                return $this->timedCall('planTask', fn () => $taskAgent->planTask($context));
            }
        }

        return $this->buildFixtureTask();
    }

    private function resolveReference(mixed $taskAgent, mixed $platform): ?string
    {
        $reference = trim((string) ($this->option('reference') ?? ''));
        if ($reference !== '') {
            return $reference;
        }

        if ($this->input->isInteractive()) {
            $mode = $this->choice(
                'How should the task reference be obtained?',
                [
                    'Create a new task first (plan + create)',
                    'Enter an existing reference',
                ],
                0
            );

            if (str_starts_with($mode, 'Enter')) {
                $reference = trim((string) $this->ask('Task reference'));
                if ($reference === '') {
                    $this->error('Reference is required.');

                    return null;
                }

                return $reference;
            }
        }

        $task = $this->resolveTaskForPlatform($taskAgent);
        $created = $platform->createTask($task);
        if (! $created || $task->getReference() === null) {
            $this->error('Failed to create a task to fetch.');

            return null;
        }

        $this->info('Created task with reference: '.$task->getReference());

        return $task->getReference();
    }

    private function resolveExistingPlatformTask(mixed $taskAgent, mixed $platform): ?Task
    {
        $reference = trim((string) ($this->option('reference') ?? ''));

        if ($reference === '' && $this->input->isInteractive()) {
            $mode = $this->choice(
                'How should the task to update be obtained?',
                [
                    'Create a new task first (plan + create)',
                    'Fetch an existing reference',
                ],
                0
            );

            if (str_starts_with($mode, 'Fetch')) {
                $reference = trim((string) $this->ask('Task reference'));
            }
        }

        if ($reference !== '') {
            $task = $platform->fetchTask($reference);
            if ($task === null) {
                $this->error("No task found for reference [{$reference}].");

                return null;
            }

            return $task;
        }

        $task = $this->resolveTaskForPlatform($taskAgent);
        if (! $platform->createTask($task)) {
            $this->error('Failed to create a task to update.');

            return null;
        }

        $this->info('Created task with reference: '.$task->getReference());

        return $task;
    }

    private function resolveTaskAction(mixed $taskAgent, Task $task): ?TaskAction
    {
        if ($this->input->isInteractive()) {
            $mode = $this->choice(
                'How should the TaskAction be obtained?',
                [
                    'Ask TaskAgent::action()',
                    'Use a local fixture action (approve)',
                ],
                0
            );

            if (str_starts_with($mode, 'Ask')) {
                return $this->timedCall('action', fn () => $taskAgent->action($task));
            }
        }

        return $this->buildFixtureAction();
    }

    private function buildContext(): SemanticContext
    {
        $rawContext = (array) $this->option('context');
        if ($rawContext !== []) {
            $context = new SemanticContext;
            foreach ($rawContext as $entry) {
                if (! is_string($entry) || ! str_contains($entry, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $entry, 2);
                $key = trim($key);
                $value = trim($value);
                if ($key === '') {
                    continue;
                }

                $context->set($key, "Live-test context value for {$key}", $value);
            }

            if ($context->toArray() !== []) {
                return $context;
            }
        }

        if ($this->input->isInteractive() && ! $this->confirm('Use default sample context?', true)) {
            $context = new SemanticContext;
            do {
                $key = trim((string) $this->ask('Context key (empty to finish)', ''));
                if ($key === '') {
                    break;
                }
                $description = (string) $this->ask('Description', "Live-test value for {$key}");
                $value = (string) $this->ask('Value');
                $context->set($key, $description, $value);
            } while (true);

            if ($context->toArray() !== []) {
                return $context;
            }
        }

        return (new SemanticContext)
            ->set('title', 'Task title', 'Review draft article outline')
            ->set(
                'description',
                'Task description',
                "Please review the attached outline for a B2B newsletter.\n\nFocus on:\n- Clarity of section goals\n- Missing evidence\n- Tone for SaaS operators"
            )
            ->set('audience', 'Target audience', 'B2B SaaS operators evaluating onboarding tooling')
            ->set('priority', 'Priority', 'normal');
    }

    private function buildFixtureTask(): Task
    {
        $context = $this->buildContext();

        return (new Task)
            ->setTitle('Review draft article outline')
            ->setDescription("Please review the attached outline for a B2B newsletter.\n\nFocus on clarity, evidence, and tone.")
            ->setStatus(TaskStatus::PENDING)
            ->setContext($context)
            ->setAssignee(
                (new Human)
                    ->setRole(Role::ASSIGNEE)
                    ->setName('Reviewer')
                    ->setEmail('reviewer@example.com')
            )
            ->addMessage(
                (new Message)
                    ->setMessage('Please start when ready.')
                    ->setHuman(
                        (new Human)
                            ->setRole(Role::OWNER)
                            ->setName('Coordinator')
                    )
            );
    }

    private function buildFixtureAction(): TaskAction
    {
        return (new TaskAction)
            ->setStatus(TaskStatus::APPROVED)
            ->setMessage(
                (new Message)
                    ->setMessage('Looks good — approved.')
                    ->setHuman(
                        (new Human)
                            ->setRole(Role::ASSIGNEE)
                            ->setName('Reviewer')
                    )
            )
            ->setOutput(
                (new TaskOutput)->setContent('Approved with minor copy edits suggested in comments.')
            );
    }

    private function displayContextSummary(SemanticContext $context): void
    {
        $this->info('Semantic context');
        $rows = [];
        foreach ($context->toArray() as $key => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $value = $entry['value'] ?? null;
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $rows[] = [
                $key,
                Str::limit((string) ($entry['description'] ?? ''), 40),
                Str::limit((string) ($value ?? ''), 72),
            ];
        }

        if ($rows !== []) {
            $this->table(['key', 'description', 'value'], $rows);
        }
        $this->line('-----');
    }

    private function displayTask(Task $task, string $label = 'Task'): void
    {
        $this->info($label);
        $this->table(
            ['Field', 'Value'],
            [
                ['reference', $task->getReference() ?? '—'],
                ['title', $task->getTitle() ?? '—'],
                ['status', $task->getStatus()->value],
                ['description', Str::limit((string) ($task->getDescription() ?? ''), 120)],
                ['assignee', $this->formatHuman($task->getAssignee())],
                ['advisor', $this->formatHuman($task->getAdvisor())],
                ['owner', $this->formatHuman($task->getOwner())],
                ['followers', (string) count($task->getFollowers())],
                ['files', (string) count($task->getFiles())],
                ['messages', (string) count($task->getMessages())],
                ['output', Str::limit((string) ($task->getOutput()?->getContent() ?? ''), 80) ?: '—'],
            ]
        );

        if ($task->getMessages() !== []) {
            $this->info('Messages');
            $rows = [];
            foreach ($task->getMessages() as $index => $message) {
                $rows[] = [
                    (string) ($index + 1),
                    $this->formatHuman($message->getHuman()),
                    Str::limit((string) ($message->getMessage() ?? ''), 80),
                    $message->getDatetime()?->toIso8601String() ?? '—',
                ];
            }
            $this->table(['#', 'human', 'message', 'datetime'], $rows);
        }

        $this->line('-----');
    }

    private function displayAction(?TaskAction $action, string $label = 'TaskAction'): void
    {
        $this->info($label);
        if ($action === null) {
            $this->line('(null — no action)');
            $this->line('-----');

            return;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['status', $action->getStatus()?->value ?? '—'],
                ['message', Str::limit((string) ($action->getMessage()?->getMessage() ?? ''), 100) ?: '—'],
                ['message_human', $this->formatHuman($action->getMessage()?->getHuman())],
                ['output', Str::limit((string) ($action->getOutput()?->getContent() ?? ''), 100) ?: '—'],
                ['output_files', (string) count($action->getOutput()?->getFiles() ?? [])],
            ]
        );
        $this->line('-----');
    }

    private function displayConclusion(TaskConclusion $conclusion, string $label = 'TaskConclusion'): void
    {
        $this->info($label);
        $this->table(
            ['Field', 'Value'],
            [
                ['conclusion', Str::limit((string) ($conclusion->getConclusion() ?? ''), 200) ?: '—'],
                ['files', (string) count($conclusion->getFiles())],
            ]
        );
        $this->line('-----');
    }

    private function formatHuman(?Human $human): string
    {
        if ($human === null) {
            return '—';
        }

        $parts = array_filter([
            $human->getRole()->value,
            $human->getName(),
            $human->getEmail(),
        ]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function timedCall(string $label, callable $callback): mixed
    {
        $start = microtime(true);
        $this->info("Calling {$label}…");
        $result = $callback();
        $this->info(sprintf('Processing time (%s): %.3f s', $label, microtime(true) - $start));
        $this->line('-----');

        return $result;
    }
}
