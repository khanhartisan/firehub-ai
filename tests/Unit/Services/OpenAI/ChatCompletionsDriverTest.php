<?php

namespace Tests\Unit\Services\OpenAI;

use App\Services\OpenAI\Drivers\ChatCompletionsDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class ChatCompletionsDriverTest extends TestCase
{
    public function test_request_structured_json_parses_chat_completion_message(): void
    {
        $body = json_encode([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode(['answer' => 'ok']),
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $mock = new MockHandler([
            new Response(200, [], $body),
        ]);

        $driver = new ChatCompletionsDriver([
            'api_key' => 'test-key',
            'base_url' => 'https://example.test/v1/',
            'model' => 'test-model',
        ]);

        $http = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://example.test/v1/']);
        $ref = new \ReflectionProperty(ChatCompletionsDriver::class, 'client');
        $ref->setAccessible(true);
        $ref->setValue($driver, $http);

        $data = $driver->requestStructuredJson(
            'Return ok',
            'test_schema',
            ['type' => 'object', 'properties' => ['answer' => ['type' => 'string']], 'required' => ['answer']],
            'Test failure',
        );

        $this->assertSame(['answer' => 'ok'], $data);
    }
}
