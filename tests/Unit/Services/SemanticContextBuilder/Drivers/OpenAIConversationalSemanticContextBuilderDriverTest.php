<?php

namespace Tests\Unit\Services\SemanticContextBuilder\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response as ResponseObject;
use App\Services\SemanticContextBuilder\Drivers\OpenAIConversationalSemanticContextBuilderDriver;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OpenAIConversationalSemanticContextBuilderDriverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_applies_suggested_updates_and_tracks_questions(): void
    {
        $context = new class() extends SemanticContext {
            public function setName(string $name): static
            {
                return $this->set('name', 'What is the name?', $name);
            }

            public function setNiches(array $niches): static
            {
                return $this->set('niches', 'What niches?', $niches);
            }
        };

        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockResponse = ResponseObject::fromArray([
            'id' => 'resp_ctx_1',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => json_encode([
                                'assistant_message' => 'Great start. I still need one detail.',
                                'is_fulfilled' => false,
                                'questions' => ['What niches does this cover?'],
                                'suggested_updates' => [
                                    ['key' => 'name', 'value' => 'Atlas Weekly'],
                                    ['key' => 'niches', 'value' => ['ai', 'automation']],
                                ],
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIConversationalSemanticContextBuilderDriver($mockOpenAIClient, ['model' => 'gpt-4o-mini']);
        $driver->setContext($context)->start('Build a context for an AI newsletter.');

        $this->assertSame('Atlas Weekly', $driver->getContext()->getNameValue());
        $this->assertSame(['ai', 'automation'], $driver->getContext()->getNichesValue());
        $this->assertFalse($driver->isFulfilled());
        $this->assertSame('What niches does this cover?', $driver->getNextQuestion());
        $this->assertCount(2, $driver->getConversation());
        $this->assertSame('assistant', $driver->getConversation()[1]['role']);
        $this->assertStringContainsString('Great start. I still need one detail.', $driver->getConversation()[1]['text']);
        $this->assertStringContainsString('What niches does this cover?', $driver->getConversation()[1]['text']);
    }

    public function test_it_includes_questions_in_conversation_when_assistant_message_is_empty(): void
    {
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn(ResponseObject::fromArray([
                'id' => 'resp_ctx_2',
                'status' => 'completed',
                'output' => [
                    [
                        'type' => 'message',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => json_encode([
                                    'assistant_message' => '',
                                    'is_fulfilled' => false,
                                    'questions' => ['Try niches like ai, automation, or robotics.'],
                                    'suggested_updates' => [],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ]));

        $driver = new OpenAIConversationalSemanticContextBuilderDriver($mockOpenAIClient);
        $driver->start('seed');

        $this->assertCount(2, $driver->getConversation());
        $this->assertSame('assistant', $driver->getConversation()[1]['role']);
        $this->assertSame('1. Try niches like ai, automation, or robotics.', $driver->getConversation()[1]['text']);
    }

    public function test_it_throws_when_openai_returns_empty_output(): void
    {
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn(ResponseObject::fromArray([
                'id' => 'resp_ctx_empty',
                'status' => 'completed',
                'output' => [],
            ]));

        $driver = new OpenAIConversationalSemanticContextBuilderDriver($mockOpenAIClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI returned empty builder response.');

        $driver->start('seed');
    }
}
