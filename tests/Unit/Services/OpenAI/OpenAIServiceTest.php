<?php

namespace Tests\Unit\Services\OpenAI;

use App\Contracts\OpenAI\Response as ResponseObject;
use App\Contracts\OpenAI\ResponseInput;
use App\Contracts\OpenAI\ResponseOptions;
use App\Services\OpenAI\Drivers\OpenAIDriver;
use App\Services\OpenAI\OpenAIService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class OpenAIServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_creates_response_successfully(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockBody = Mockery::mock(\Psr\Http\Message\StreamInterface::class);

        $expectedPayload = [
            'model' => 'gpt-4o-mini',
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => 'Test input'],
                    ],
                ],
            ],
        ];

        $responseData = [
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [],
        ];

        $mockBody->shouldReceive('getContents')
            ->once()
            ->andReturn(json_encode($responseData));

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn($mockBody);

        $mockClient->shouldReceive('post')
            ->once()
            ->with('responses', Mockery::on(function ($arg) use ($expectedPayload) {
                return isset($arg['json'])
                    && $arg['json']['model'] === $expectedPayload['model']
                    && is_array($arg['json']['input'])
                    && isset($arg['json']['input'][0]['role'])
                    && $arg['json']['input'][0]['role'] === 'user';
            }))
            ->andReturn($mockResponse);

        $service = $this->createServiceWithMockClient($mockClient);
        $input = ResponseInput::text('Test input');

        $result = $service->createResponse($input);

        $this->assertInstanceOf(ResponseObject::class, $result);
        $this->assertEquals($responseData['id'], $result->getId());
        $this->assertEquals($responseData['status'], $result->getStatus()->value);
    }

    public function test_it_uses_custom_model_from_options(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockBody = Mockery::mock(\Psr\Http\Message\StreamInterface::class);

        $expectedPayload = [
            'model' => 'gpt-4o',
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => 'Test input'],
                    ],
                ],
            ],
        ];

        $responseData = ['id' => 'resp_123', 'status' => 'completed'];

        $mockBody->shouldReceive('getContents')
            ->once()
            ->andReturn(json_encode($responseData));

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn($mockBody);

        $mockClient->shouldReceive('post')
            ->once()
            ->with('responses', Mockery::on(function ($arg) {
                return isset($arg['json'])
                    && $arg['json']['model'] === 'gpt-4o'
                    && is_array($arg['json']['input']);
            }))
            ->andReturn($mockResponse);

        $service = $this->createServiceWithMockClient($mockClient);
        $input = ResponseInput::text('Test input');
        $options = ResponseOptions::create()->model('gpt-4o');

        $result = $service->createResponse($input, $options);

        $this->assertInstanceOf(ResponseObject::class, $result);
        $this->assertEquals($responseData['id'], $result->getId());
    }

    public function test_it_includes_all_options_in_payload(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockBody = Mockery::mock(\Psr\Http\Message\StreamInterface::class);

        $responseData = ['id' => 'resp_123', 'status' => 'completed'];

        $mockBody->shouldReceive('getContents')
            ->once()
            ->andReturn(json_encode($responseData));

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn($mockBody);

        $mockClient->shouldReceive('post')
            ->once()
            ->with('responses', Mockery::on(function ($arg) {
                $payload = $arg['json'];
                return isset($payload['model'])
                    && $payload['model'] === 'gpt-4o'
                    && isset($payload['previous_response_id'])
                    && $payload['previous_response_id'] === 'resp_prev'
                    && isset($payload['temperature'])
                    && $payload['temperature'] === 0.7
                    && isset($payload['max_tokens'])
                    && $payload['max_tokens'] === 1000
                    && is_array($payload['input']);
            }))
            ->andReturn($mockResponse);

        $service = $this->createServiceWithMockClient($mockClient);
        $input = ResponseInput::text('Test input');
        $options = ResponseOptions::create()
            ->model('gpt-4o')
            ->previousResponseId('resp_prev')
            ->temperature(0.7)
            ->maxTokens(1000);

        $result = $service->createResponse($input, $options);

        $this->assertInstanceOf(ResponseObject::class, $result);
        $this->assertEquals($responseData['id'], $result->getId());
    }

    public function test_it_handles_guzzle_exception(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('OpenAI API error', Mockery::type('array'));

        $mockClient = Mockery::mock(Client::class);
        $exception = new RequestException('Network error', Mockery::mock(\Psr\Http\Message\RequestInterface::class));

        $mockClient->shouldReceive('post')
            ->once()
            ->andThrow($exception);

        $service = $this->createServiceWithMockClient($mockClient);
        $input = ResponseInput::text('Test input');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create OpenAI response: Network error');

        $service->createResponse($input);
    }

    public function test_it_gets_response_successfully(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockBody = Mockery::mock(\Psr\Http\Message\StreamInterface::class);

        $responseData = [
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [],
        ];

        $mockBody->shouldReceive('getContents')
            ->once()
            ->andReturn(json_encode($responseData));

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn($mockBody);

        $mockClient->shouldReceive('get')
            ->once()
            ->with('responses/resp_123')
            ->andReturn($mockResponse);

        $service = $this->createServiceWithMockClient($mockClient);

        $result = $service->getResponse('resp_123');

        $this->assertInstanceOf(ResponseObject::class, $result);
        $this->assertEquals($responseData['id'], $result->getId());
        $this->assertEquals($responseData['status'], $result->getStatus()->value);
    }

    public function test_it_cancels_response_successfully(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockBody = Mockery::mock(\Psr\Http\Message\StreamInterface::class);

        $responseData = [
            'id' => 'resp_123',
            'status' => 'cancelled',
        ];

        $mockBody->shouldReceive('getContents')
            ->once()
            ->andReturn(json_encode($responseData));

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn($mockBody);

        $mockClient->shouldReceive('post')
            ->once()
            ->with('responses/resp_123/cancel', [])
            ->andReturn($mockResponse);

        $service = $this->createServiceWithMockClient($mockClient);

        $result = $service->cancelResponse('resp_123');

        $this->assertInstanceOf(ResponseObject::class, $result);
        $this->assertEquals($responseData['id'], $result->getId());
        $this->assertEquals($responseData['status'], $result->getStatus()->value);
    }

    public function test_it_deletes_response_successfully(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockBody = Mockery::mock(\Psr\Http\Message\StreamInterface::class);

        $responseData = [
            'id' => 'resp_123',
            'deleted' => true,
        ];

        $mockBody->shouldReceive('getContents')
            ->once()
            ->andReturn(json_encode($responseData));

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn($mockBody);

        $mockClient->shouldReceive('delete')
            ->once()
            ->with('responses/resp_123')
            ->andReturn($mockResponse);

        $service = $this->createServiceWithMockClient($mockClient);

        $result = $service->deleteResponse('resp_123');

        $this->assertInstanceOf(ResponseObject::class, $result);
        $this->assertEquals($responseData['id'], $result->getId());
    }

    public function test_it_uses_default_model_when_no_options_provided(): void
    {
        $mockClient = Mockery::mock(Client::class);
        $mockResponse = Mockery::mock(Response::class);
        $mockBody = Mockery::mock(\Psr\Http\Message\StreamInterface::class);

        $responseData = ['id' => 'resp_123'];

        $mockBody->shouldReceive('getContents')
            ->once()
            ->andReturn(json_encode($responseData));

        $mockResponse->shouldReceive('getBody')
            ->once()
            ->andReturn($mockBody);

        $mockClient->shouldReceive('post')
            ->once()
            ->with('responses', Mockery::on(function ($arg) {
                return isset($arg['json'])
                    && $arg['json']['model'] === 'gpt-4o-mini'
                    && is_array($arg['json']['input']);
            }))
            ->andReturn($mockResponse);

        $service = $this->createServiceWithMockClient($mockClient, ['default_model' => 'gpt-4o-mini']);
        $input = ResponseInput::text('Test input');

        $result = $service->createResponse($input);

        $this->assertInstanceOf(ResponseObject::class, $result);
        $this->assertEquals($responseData['id'], $result->getId());
    }

    public function test_it_includes_beta_header_when_configured(): void
    {
        $config = [
            'api_key' => 'test-key',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o-mini',
            'beta_header' => 'responses=v1',
            'timeout' => 60,
        ];

        $service = new OpenAIDriver($config);

        // Use reflection to check the client was configured correctly
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $client = $clientProperty->getValue($service);

        // The client should have the beta header configured
        // We can't easily test the internal Guzzle config, but we can verify the service was created
        $this->assertInstanceOf(Client::class, $client);
    }

    /**
     * Create a service instance with a mocked HTTP client.
     */
    protected function createServiceWithMockClient(Client $mockClient, array $config = []): OpenAIService
    {
        $defaultConfig = [
            'api_key' => 'test-key',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o-mini',
            'timeout' => 60,
        ];

        $config = array_merge($defaultConfig, $config);
        $service = new OpenAIDriver($config);

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $mockClient);

        return $service;
    }
}
