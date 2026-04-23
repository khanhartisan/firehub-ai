<?php

namespace Tests\Unit\Services\FactChecker\Drivers;

use App\Contracts\CommonData\Point;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\CommonData\Verification;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response as ResponseObject;
use App\Services\FactChecker\Drivers\OpenAIFactCheckerDriver;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OpenAIFactCheckerDriverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_verifies_point_successfully(): void
    {
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockResponse = ResponseObject::fromArray([
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => json_encode([
                                'is_valid' => true,
                                'confidence' => 0.91,
                                'reasoning' => 'The claim is supported by multiple consistent evidence lines.',
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIFactCheckerDriver($mockOpenAIClient, ['model' => 'gpt-4o-mini']);

        $point = (new Point)
            ->setHeadline('Claim headline')
            ->setDescription('Claim details')
            ->setEvidences(['Evidence 1', 'Evidence 2']);

        $result = $driver->verify($point);

        $this->assertInstanceOf(Verification::class, $result);
        $this->assertTrue($result->getIsValid());
        $this->assertSame(0.91, $result->getConfidence());
        $this->assertNotNull($result->getReasoning());
    }

    public function test_it_uses_custom_model_from_config(): void
    {
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockResponse = ResponseObject::fromArray([
            'id' => 'resp_456',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => json_encode([
                                'is_valid' => false,
                                'confidence' => 0.2,
                                'reasoning' => 'Insufficient evidence.',
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->with(
                Mockery::type(\App\Contracts\OpenAI\ResponseInput::class),
                Mockery::on(function ($options) {
                    return $options->getModel() === 'gpt-4o';
                })
            )
            ->andReturn($mockResponse);

        $driver = new OpenAIFactCheckerDriver($mockOpenAIClient, ['model' => 'gpt-4o']);

        $point = (new Point)
            ->setHeadline('Claim headline')
            ->setEvidences(['Evidence 1']);

        $result = $driver->verify($point, new SemanticContext());

        $this->assertFalse($result->getIsValid());
    }

    public function test_it_throws_exception_when_openai_returns_empty_response(): void
    {
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockResponse = ResponseObject::fromArray([
            'id' => 'resp_789',
            'status' => 'completed',
            'output' => [],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIFactCheckerDriver($mockOpenAIClient, ['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI returned empty fact-check response');

        $driver->verify((new Point)->setHeadline('Claim')->setEvidences(['Evidence']));
    }

    public function test_it_throws_exception_when_response_contains_refusal(): void
    {
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockResponse = ResponseObject::fromArray([
            'id' => 'resp_999',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'refusal',
                            'refusal' => 'Cannot verify this.',
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIFactCheckerDriver($mockOpenAIClient);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI refused the fact-check request');

        $driver->verify((new Point)->setHeadline('Claim')->setEvidences(['Evidence']));
    }
}
