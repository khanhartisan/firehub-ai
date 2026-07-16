<?php

namespace Tests\Unit\Services\HitlGateway\TaskAgentDrivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\HitlGateway\Role;
use App\Contracts\HitlGateway\Task;
use App\Contracts\HitlGateway\TaskAction;
use App\Contracts\HitlGateway\TaskStatus;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Models\File;
use App\Services\HitlGateway\TaskAgentDrivers\OpenAITaskAgent;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OpenAITaskAgentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_plan_task_hydrates_structured_response_and_attaches_input_files(): void
    {
        $responsePayload = json_encode([
            'title' => 'Review draft',
            'description' => 'Please review the outline.',
            'assignee' => [
                'role' => Role::ASSIGNEE->value,
                'email' => 'ada@example.com',
                'name' => 'Ada',
                'description' => null,
            ],
            'advisor' => null,
            'owner' => null,
            'followers' => [],
            'messages' => [],
        ], JSON_THROW_ON_ERROR);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($this->completedResponse($responsePayload));

        $file = new File;
        $file->forceFill(['id' => 'file_1', 'url' => 'https://example.com/a.png']);
        $file->exists = true;

        $context = (new SemanticContext)
            ->set('title', 'Task title', 'Review draft')
            ->set('topic', 'Topic', 'AI');

        $driver = new OpenAITaskAgent($client, ['model' => 'gpt-4o-mini']);
        $task = $driver->planTask($context, [$file]);

        $this->assertSame('Review draft', $task->getTitle());
        $this->assertSame('Please review the outline.', $task->getDescription());
        $this->assertSame(TaskStatus::PENDING, $task->getStatus());
        $this->assertSame('Ada', $task->getAssignee()?->getName());
        $this->assertSame($context, $task->getContext());
        $this->assertCount(1, $task->getFiles());
        $this->assertSame('file_1', $task->getFiles()[0]->getKey());
    }

    public function test_plan_task_rejects_empty_context(): void
    {
        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldNotReceive('createResponse');

        $driver = new OpenAITaskAgent($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Task context cannot be empty.');
        $driver->planTask(new SemanticContext);
    }

    public function test_action_returns_null_when_should_act_is_false(): void
    {
        $payload = json_encode([
            'should_act' => false,
            'action' => null,
        ], JSON_THROW_ON_ERROR);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($this->completedResponse($payload));

        $driver = new OpenAITaskAgent($client);
        $action = $driver->action((new Task)->setStatus(TaskStatus::DOING)->setTitle('In progress'));

        $this->assertNull($action);
    }

    public function test_action_returns_task_action_when_should_act_is_true(): void
    {
        $payload = json_encode([
            'should_act' => true,
            'action' => [
                'status' => TaskStatus::DOING->value,
                'message' => [
                    'human' => null,
                    'message' => 'Starting work',
                    'datetime' => null,
                ],
                'output' => null,
            ],
        ], JSON_THROW_ON_ERROR);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($this->completedResponse($payload));

        $driver = new OpenAITaskAgent($client);
        $action = $driver->action((new Task)->setStatus(TaskStatus::PENDING)->setTitle('Review'));

        $this->assertInstanceOf(TaskAction::class, $action);
        $this->assertSame(TaskStatus::DOING, $action->getStatus());
        $this->assertSame('Starting work', $action->getMessage()?->getMessage());
    }

    public function test_action_skips_openai_for_terminal_statuses(): void
    {
        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldNotReceive('createResponse');

        $driver = new OpenAITaskAgent($client);

        $this->assertNull($driver->action((new Task)->setStatus(TaskStatus::APPROVED)));
        $this->assertNull($driver->action((new Task)->setStatus(TaskStatus::REJECTED)));
    }

    public function test_action_respects_auto_action_config(): void
    {
        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldNotReceive('createResponse');

        $driver = new OpenAITaskAgent($client, ['auto_action' => false]);

        $this->assertNull($driver->action((new Task)->setStatus(TaskStatus::PENDING)));
    }

    public function test_refusal_throws_runtime_exception(): void
    {
        $response = Response::fromArray([
            'id' => 'resp_refusal',
            'created_at' => time(),
            'status' => 'completed',
            'model' => 'gpt-4o-mini',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'refusal',
                            'refusal' => 'Cannot help with that.',
                        ],
                    ],
                ],
            ],
        ]);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($response);

        $driver = new OpenAITaskAgent($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI refused the request');
        $driver->planTask((new SemanticContext)->set('request', 'Request', 'Do something unsafe'));
    }

    protected function completedResponse(string $text): Response
    {
        return Response::fromArray([
            'id' => 'resp_1',
            'created_at' => time(),
            'status' => 'completed',
            'model' => 'gpt-4o-mini',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => $text,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
