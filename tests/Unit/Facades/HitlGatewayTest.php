<?php

namespace Tests\Unit\Facades;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskAgent;
use App\Contracts\HitlGateway\TaskConclusion;
use App\Contracts\HitlGateway\TaskOutput;
use App\Contracts\HitlGateway\TaskStatus;
use App\Facades\HitlGateway;
use App\Models\File;
use App\Models\HitlPlatform;
use App\Models\HitlTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HitlGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ask_human_creates_pending_task_and_returns_null(): void
    {
        $platform = $this->makePlatform();
        $agent = Mockery::mock(TaskAgent::class);

        $agent->shouldReceive('planTask')
            ->once()
            ->with(Mockery::type(SemanticContext::class), [])
            ->andReturn(
                (new Task)
                    ->setTitle('Review draft')
                    ->setDescription('Please review')
                    ->setStatus(TaskStatus::PENDING)
            );

        $agent->shouldReceive('conclude')->never();

        $result = HitlGateway::askHuman(
            'internal-1',
            $platform,
            $agent,
            (new SemanticContext)->set('title', 'Task title', 'Review draft')
        );

        $this->assertNull($result);

        $hitlTask = HitlTask::query()->sole();
        $this->assertSame($platform->id, $hitlTask->hitl_platform_id);
        $this->assertSame('internal-1', $hitlTask->internal_reference);
        $this->assertSame('Review draft', $hitlTask->title);
        $this->assertSame('Please review', $hitlTask->description);
        $this->assertSame(TaskStatus::PENDING, $hitlTask->status);
        $this->assertNotNull($hitlTask->hitl_platform_reference);
        $this->assertStringStartsWith('dummy-', $hitlTask->hitl_platform_reference);
        $this->assertNull($hitlTask->conclusion);
    }

    public function test_ask_human_returns_null_while_task_is_doing(): void
    {
        $platform = $this->makePlatform();
        $agent = Mockery::mock(TaskAgent::class);

        $agent->shouldReceive('planTask')
            ->once()
            ->andReturn(
                (new Task)
                    ->setTitle('In progress')
                    ->setDescription('Working')
                    ->setStatus(TaskStatus::DOING)
            );

        $this->assertNull(HitlGateway::askHuman(
            'internal-doing',
            $platform,
            $agent,
            new SemanticContext
        ));

        $this->assertSame(TaskStatus::DOING, HitlTask::query()->sole()->status);
    }

    public function test_ask_human_returns_fixed_conclusion_when_rejected(): void
    {
        $platform = $this->makePlatform();
        $agent = Mockery::mock(TaskAgent::class);

        $agent->shouldReceive('planTask')
            ->once()
            ->andReturn(
                (new Task)
                    ->setTitle('Rejected task')
                    ->setDescription('Cannot answer')
                    ->setStatus(TaskStatus::REJECTED)
            );

        $agent->shouldReceive('conclude')->never();

        $result = HitlGateway::askHuman(
            'internal-rejected',
            $platform,
            $agent,
            new SemanticContext
        );

        $this->assertInstanceOf(TaskConclusion::class, $result);
        $this->assertSame('Human was unable to answer', $result->getConclusion());

        $hitlTask = HitlTask::query()->sole();
        $this->assertSame(TaskStatus::REJECTED, $hitlTask->status);
        $this->assertNull($hitlTask->conclusion);
    }

    public function test_ask_human_concludes_when_approved_on_create(): void
    {
        $platform = $this->makePlatform();
        $agent = Mockery::mock(TaskAgent::class);

        $planned = (new Task)
            ->setTitle('Approved task')
            ->setDescription('Looks good')
            ->setStatus(TaskStatus::COMPLETED)
            ->setOutput((new TaskOutput)->setContent('Ship it'));

        $agent->shouldReceive('planTask')->once()->andReturn($planned);
        $agent->shouldReceive('conclude')
            ->once()
            ->with(Mockery::on(fn (Task $task) => $task->getStatus() === TaskStatus::COMPLETED))
            ->andReturn((new TaskConclusion)->setConclusion('Approved by human'));

        $result = HitlGateway::askHuman(
            'internal-approved',
            $platform,
            $agent,
            new SemanticContext
        );

        $this->assertInstanceOf(TaskConclusion::class, $result);
        $this->assertSame('Approved by human', $result->getConclusion());

        $hitlTask = HitlTask::query()->sole();
        $this->assertSame(TaskStatus::COMPLETED, $hitlTask->status);
        $this->assertSame('Approved by human', $hitlTask->conclusion['conclusion'] ?? null);
        $this->assertSame([], $hitlTask->conclusion['files'] ?? null);
    }

    public function test_ask_human_reuses_existing_platform_task_without_planning_again(): void
    {
        $platform = $this->makePlatform();
        $agent = Mockery::mock(TaskAgent::class);

        $agent->shouldReceive('planTask')
            ->once()
            ->andReturn(
                (new Task)
                    ->setTitle('First pass')
                    ->setDescription('Waiting')
                    ->setStatus(TaskStatus::PENDING)
            );

        $this->assertNull(HitlGateway::askHuman(
            'internal-reuse',
            $platform,
            $agent,
            new SemanticContext
        ));

        $hitlTask = HitlTask::query()->sole();
        $manager = $platform->getHitlPlatformManager();
        $platformTask = $manager->fetchTask($hitlTask->hitl_platform_reference);
        $this->assertNotNull($platformTask);

        $this->assertNotNull($manager->updateTask(
            $platformTask->getReference(),
            (new TaskAction)
                ->setStatus(TaskStatus::COMPLETED)
                ->setOutput((new TaskOutput)->setContent('Final answer'))
        ));

        $agent->shouldReceive('planTask')->never();
        $agent->shouldReceive('conclude')
            ->once()
            ->andReturn((new TaskConclusion)->setConclusion('Final answer'));

        $result = HitlGateway::askHuman(
            'internal-reuse',
            $platform,
            $agent,
            new SemanticContext
        );

        $this->assertSame('Final answer', $result?->getConclusion());
        $this->assertSame(1, HitlTask::query()->count());
        $this->assertSame(TaskStatus::COMPLETED, $hitlTask->fresh()->status);
    }

    public function test_ask_human_recreates_when_platform_task_is_missing(): void
    {
        $platform = $this->makePlatform();
        $agent = Mockery::mock(TaskAgent::class);

        HitlTask::query()->create([
            'hitl_platform_id' => $platform->id,
            'internal_reference' => 'internal-missing',
            'hitl_platform_reference' => 'missing-ref',
            'title' => 'Stale',
            'description' => 'Gone from platform',
            'status' => TaskStatus::PENDING,
        ]);

        $agent->shouldReceive('planTask')
            ->once()
            ->andReturn(
                (new Task)
                    ->setTitle('Recreated')
                    ->setDescription('New platform task')
                    ->setStatus(TaskStatus::PENDING)
            );

        $this->assertNull(HitlGateway::askHuman(
            'internal-missing',
            $platform,
            $agent,
            new SemanticContext
        ));

        $hitlTask = HitlTask::query()->sole();
        $this->assertSame('Recreated', $hitlTask->title);
        $this->assertNotSame('missing-ref', $hitlTask->hitl_platform_reference);
        $this->assertStringStartsWith('dummy-', $hitlTask->hitl_platform_reference);
    }

    public function test_ask_human_filters_non_file_attachments_before_planning(): void
    {
        $platform = $this->makePlatform();
        $agent = Mockery::mock(TaskAgent::class);
        $file = new File;

        $agent->shouldReceive('planTask')
            ->once()
            ->withArgs(function (SemanticContext $context, array $files) use ($file): bool {
                $files = array_values($files);

                return count($files) === 1 && $files[0] === $file;
            })
            ->andReturn(
                (new Task)
                    ->setTitle('With file')
                    ->setDescription('Has attachment')
                    ->setStatus(TaskStatus::PENDING)
            );

        $this->assertNull(HitlGateway::askHuman(
            'internal-files',
            $platform,
            $agent,
            new SemanticContext,
            [$file, 'not-a-file', 123]
        ));
    }

    public function test_ask_human_throws_when_platform_create_fails(): void
    {
        $platform = $this->makePlatform();
        $manager = $platform->getHitlPlatformManager();

        $conflicting = (new Task)
            ->setReference('fixed-ref')
            ->setTitle('Existing')
            ->setDescription('Already there')
            ->setStatus(TaskStatus::PENDING);
        $this->assertTrue($manager->createTask($conflicting));

        $agent = Mockery::mock(TaskAgent::class);
        $agent->shouldReceive('planTask')
            ->once()
            ->andReturn(
                (new Task)
                    ->setReference('fixed-ref')
                    ->setTitle('Conflict')
                    ->setDescription('Will fail create')
                    ->setStatus(TaskStatus::PENDING)
            );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to create HITL task on platform');

        HitlGateway::askHuman(
            'internal-conflict',
            $platform,
            $agent,
            new SemanticContext
        );
    }

    protected function makePlatform(): HitlPlatform
    {
        return HitlPlatform::query()->create([
            'name' => 'Test HITL Platform '.uniqid(),
            'driver' => 'dummy',
            'is_active' => true,
        ]);
    }
}
