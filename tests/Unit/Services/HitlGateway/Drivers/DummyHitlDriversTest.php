<?php

namespace Tests\Unit\Services\HitlGateway\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\Message;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskConclusion;
use App\Contracts\HitlGateway\TaskOutput;
use App\Contracts\HitlGateway\TaskStatus;
use App\Services\HitlGateway\HitlPlatformManagerDrivers\DummyHitlPlatformManager;
use App\Services\HitlGateway\TaskAgentDrivers\DummyTaskAgent;
use Tests\TestCase;

class DummyHitlDriversTest extends TestCase
{
    public function test_task_agent_plans_task_from_context(): void
    {
        $agent = new DummyTaskAgent;
        $context = (new SemanticContext)
            ->set('title', 'Task title', 'Review draft')
            ->set('description', 'Task description', "Review draft\nPlease check the outline.");

        $task = $agent->planTask($context);

        $this->assertSame('Review draft', $task->getTitle());
        $this->assertSame("Review draft\nPlease check the outline.", $task->getDescription());
        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
        $this->assertSame($context, $task->getContext());
    }

    public function test_task_agent_returns_doing_action_for_pending_task(): void
    {
        $agent = new DummyTaskAgent(['auto_action' => true]);

        $action = $agent->action((new Task)->setStatus(TaskStatus::PENDING));

        $this->assertInstanceOf(TaskAction::class, $action);
        $this->assertSame(TaskStatus::DOING, $action->getStatus());
    }

    public function test_task_agent_concludes_from_output_or_status(): void
    {
        $agent = new DummyTaskAgent;

        $withOutput = $agent->conclude(
            (new Task)
                ->setStatus(TaskStatus::APPROVED)
                ->setOutput((new TaskOutput)->setContent('Approved with edits.'))
        );

        $this->assertInstanceOf(TaskConclusion::class, $withOutput);
        $this->assertSame('Approved with edits.', $withOutput->getConclusion());

        $fromStatus = $agent->conclude((new Task)->setStatus(TaskStatus::PENDING));
        $this->assertSame('Task is pending.', $fromStatus->getConclusion());
    }

    public function test_platform_manager_creates_fetches_and_updates_tasks_in_memory(): void
    {
        $platform = new DummyHitlPlatformManager;

        $task = (new Task)
            ->setTitle('Review')
            ->setDescription('Please review')
            ->setStatus(TaskStatus::PENDING);

        $this->assertTrue($platform->createTask($task));
        $this->assertNotNull($task->getReference());
        $this->assertStringStartsWith('dummy-', $task->getReference());

        $fetched = $platform->fetchTask($task->getReference());
        $this->assertNotNull($fetched);
        $this->assertSame('Review', $fetched->getTitle());

        $action = (new TaskAction)
            ->setStatus(TaskStatus::APPROVED)
            ->setMessage((new Message)->setMessage('Looks good'))
            ->setOutput((new TaskOutput)->setContent('Approved output'));

        $this->assertTrue($platform->updateTask($task, $action));
        $this->assertSame(TaskStatus::APPROVED, $task->getStatus());
        $this->assertCount(1, $task->getMessages());
        $this->assertSame('Approved output', $task->getOutput()->getContent());

        $updated = $platform->fetchTask($task->getReference());
        $this->assertSame(TaskStatus::APPROVED, $updated->getStatus());
        $this->assertSame('Looks good', $updated->getMessages()[0]->getMessage());
    }

    public function test_platform_manager_rejects_duplicate_reference_on_create(): void
    {
        $platform = new DummyHitlPlatformManager;

        $first = (new Task)->setReference('task-1')->setTitle('First');
        $second = (new Task)->setReference('task-1')->setTitle('Second');

        $this->assertTrue($platform->createTask($first));
        $this->assertFalse($platform->createTask($second));
    }
}
