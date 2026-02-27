<?php

namespace Tests\Unit\Services\VerticalResolver\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response as ResponseObject;
use App\Contracts\VerticalResolver\Vertical;
use App\Contracts\VerticalResolver\VerticalMatch;
use App\Services\VerticalResolver\Drivers\OpenAIVerticalResolverDriver;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OpenAIVerticalResolverDriverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function vertical(string $name, ?string $description = null, ?string $identifier = null): Vertical
    {
        $v = new Vertical($name, $description);
        if ($identifier !== null) {
            $v->setIdentifier($identifier);
        }
        return $v;
    }

    public function test_it_resolves_verticals_successfully(): void
    {
        $verticals = [
            $this->vertical('News', 'News articles and headlines'),
            $this->vertical('Docs', 'Documentation and technical docs'),
        ];

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
                                'matches' => [
                                    ['vertical_identifier' => 'News', 'confidence' => 0.9],
                                    ['vertical_identifier' => 'Docs', 'confidence' => 0.3],
                                ],
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIVerticalResolverDriver($mockOpenAIClient, [
            'model' => 'gpt-4o-mini',
            'match_threshold' => 0.2,
        ]);

        $result = $driver->resolve('This page contains news articles and documentation.', $verticals);

        $this->assertIsArray($result);
        $this->assertContainsOnlyInstancesOf(VerticalMatch::class, $result);
        $this->assertCount(2, $result);
        $this->assertSame('News', $result[0]->getVerticalIdentifier());
        $this->assertEqualsWithDelta(0.9, $result[0]->getConfidence(), 0.001);
        $this->assertSame('Docs', $result[1]->getVerticalIdentifier());
    }

    public function test_it_returns_empty_array_when_no_verticals(): void
    {
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockOpenAIClient->shouldNotReceive('createResponse');

        $driver = new OpenAIVerticalResolverDriver($mockOpenAIClient, [
            'model' => 'gpt-4o-mini',
        ]);

        $result = $driver->resolve('Some content', []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_it_uses_vertical_identifier_in_schema(): void
    {
        $verticals = [
            $this->vertical('News', 'News articles', 'news-id'),
        ];

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
                                'matches' => [
                                    ['vertical_identifier' => 'news-id', 'confidence' => 0.85],
                                ],
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
                    $format = $options->getResponseFormat();
                    if ($format === null || $format['type'] !== 'json_schema' || $format['name'] !== 'vertical_resolution') {
                        return false;
                    }
                    $schema = $format['schema'] ?? [];
                    $matchesSchema = $schema['properties']['matches'] ?? [];
                    $itemSchema = $matchesSchema['items'] ?? [];
                    $enum = $itemSchema['properties']['vertical_identifier']['enum'] ?? [];
                    return in_array('news-id', $enum, true);
                })
            )
            ->andReturn($mockResponse);

        $driver = new OpenAIVerticalResolverDriver($mockOpenAIClient, [
            'model' => 'gpt-4o-mini',
        ]);

        $result = $driver->resolve('Some news content', $verticals);

        $this->assertCount(1, $result);
        $this->assertSame('news-id', $result[0]->getVerticalIdentifier());
    }

    public function test_it_throws_exception_when_openai_api_fails(): void
    {
        $verticals = [$this->vertical('News', 'News articles and headlines')];

        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andThrow(new \Exception('API connection failed'));

        $driver = new OpenAIVerticalResolverDriver($mockOpenAIClient, [
            'model' => 'gpt-4o-mini',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to resolve verticals with OpenAI');

        $driver->resolve('Some content', $verticals);
    }

    public function test_it_throws_exception_when_openai_returns_empty_response(): void
    {
        $verticals = [$this->vertical('News', 'News articles and headlines')];

        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockResponse = ResponseObject::fromArray([
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIVerticalResolverDriver($mockOpenAIClient, [
            'model' => 'gpt-4o-mini',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI returned empty vertical resolution response');

        $driver->resolve('Some content', $verticals);
    }

    public function test_it_throws_exception_when_response_contains_refusal(): void
    {
        $verticals = [$this->vertical('News', 'News articles and headlines')];

        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockResponse = ResponseObject::fromArray([
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'refusal',
                            'refusal' => 'I cannot resolve this content.',
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIVerticalResolverDriver($mockOpenAIClient, [
            'model' => 'gpt-4o-mini',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI refused to resolve verticals');

        $driver->resolve('Some content', $verticals);
    }

    public function test_it_throws_exception_for_invalid_json_response(): void
    {
        $verticals = [$this->vertical('News', 'News articles and headlines')];

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
                            'text' => 'Invalid JSON response',
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIVerticalResolverDriver($mockOpenAIClient, [
            'model' => 'gpt-4o-mini',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse vertical resolution response as JSON');

        $driver->resolve('Some content', $verticals);
    }

    public function test_it_applies_match_threshold(): void
    {
        $verticals = [$this->vertical('News', 'News articles')];

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
                                'matches' => [
                                    ['vertical_identifier' => 'News', 'confidence' => 0.9],
                                    ['vertical_identifier' => 'Docs', 'confidence' => 0.2],
                                ],
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')->once()->andReturn($mockResponse);

        $driver = new OpenAIVerticalResolverDriver($mockOpenAIClient, [
            'model' => 'gpt-4o-mini',
            'match_threshold' => 0.5,
        ]);

        $result = $driver->resolve('News content', $verticals);

        $this->assertCount(1, $result);
        $this->assertSame('News', $result[0]->getVerticalIdentifier());
    }

    public function test_it_uses_custom_model_from_config(): void
    {
        $verticals = [$this->vertical('News', 'News articles')];

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
                            'text' => json_encode(['matches' => []]),
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

        $driver = new OpenAIVerticalResolverDriver($mockOpenAIClient, [
            'model' => 'gpt-4o',
        ]);

        $result = $driver->resolve('Some content', $verticals);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_propose_returns_contract_verticals(): void
    {
        $verticals = [$this->vertical('News', 'News')];

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
                                'proposals' => [
                                    ['name' => 'tech_news', 'description' => 'Technology news and updates'],
                                    ['name' => 'product_docs', 'description' => 'Product documentation'],
                                ],
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIVerticalResolverDriver($mockOpenAIClient, [
            'model' => 'gpt-4o-mini',
        ]);

        $result = $driver->propose('Technology news and product documentation.', $verticals);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Vertical::class, $result);
        $this->assertSame('tech_news', $result[0]->getName());
        $this->assertSame('Technology news and updates', $result[0]->getDescription());
        $this->assertSame('product_docs', $result[1]->getName());
        $this->assertSame('Product documentation', $result[1]->getDescription());
    }

    public function test_propose_returns_empty_when_no_suggestions(): void
    {
        $verticals = [];

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
                            'text' => json_encode(['proposals' => []]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')->once()->andReturn($mockResponse);

        $driver = new OpenAIVerticalResolverDriver($mockOpenAIClient, ['model' => 'gpt-4o-mini']);

        $result = $driver->propose('Some content', $verticals);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
