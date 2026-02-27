<?php

namespace Tests\Unit\Services\ScrapePolicyEngine\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response as ResponseObject;
use App\Contracts\ScrapePolicyEngine\PolicyResult;
use App\Enums\ContentType;
use App\Enums\EntityType;
use App\Enums\PageType;
use App\Enums\ScrapingStatus;
use App\Enums\Temporal;
use App\Models\Entity;
use App\Models\Snapshot;
use App\Models\Source;
use App\Services\ScrapePolicyEngine\Drivers\OpenAIScrapePolicyEngineDriver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OpenAIScrapePolicyEngineDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_evaluates_an_entity_successfully(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'type' => EntityType::PAGE,
            'page_type' => PageType::DETAIL,
            'content_type' => ContentType::ARTICLE,
            'temporal' => Temporal::BREAKING,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

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
                                'value_boost' => 0.8,
                                'priority' => 0.9,
                                'urgency' => 0.7,
                                'next_scrape_at_hours' => 6,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);
        $baseTime = Carbon::now();

        $result = $driver->evaluate($entity, $baseTime);

        $this->assertInstanceOf(PolicyResult::class, $result);
        $this->assertEquals(0.8, $result->getValueBoost());
        $this->assertEquals(0.9, $result->getPriority());
        $this->assertEquals(0.7, $result->getUrgency());
        $this->assertNotNull($result->getNextScrapeAt());
        $this->assertEquals($baseTime->copy()->addHours(6)->format('Y-m-d H:i:s'), $result->getNextScrapeAt()->format('Y-m-d H:i:s'));
    }

    public function test_it_calculates_change_boost_from_snapshots(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

        // Create snapshots with change percentages
        Snapshot::create([
            'entity_id' => $entity->id,
            'content_change_percentage' => 75.0, // 75% change
        ]);
        Snapshot::create([
            'entity_id' => $entity->id,
            'content_change_percentage' => 50.0, // 50% change
        ]);

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
                                'value_boost' => 0.5,
                                'priority' => 0.5,
                                'urgency' => 0.5,
                                'next_scrape_at_hours' => 24,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->evaluate($entity);

        // Average change percentage: (75 + 50) / 2 = 62.5%
        // Change boost: 62.5 / 100 = 0.625
        $this->assertEqualsWithDelta(0.625, $result->getChangeBoost(), 0.01);
    }

    public function test_it_calculates_cost_factor_from_snapshots(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

        // Create snapshots with cost data
        Snapshot::create([
            'entity_id' => $entity->id,
            'cost' => 5.0,
            'content_length' => 500000,
            'media_count' => 10,
            'fetch_duration_ms' => 5000,
            'structured_data_count' => 5,
        ]);

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
                                'value_boost' => 0.5,
                                'priority' => 0.5,
                                'urgency' => 0.5,
                                'next_scrape_at_hours' => 24,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->evaluate($entity);

        // Cost factor should be calculated based on cost, content length, etc.
        // With cost=5.0 (normalized to 0.5), content_length=500000 (normalized to 0.5), etc.
        // Should result in a cost factor > 0
        $this->assertGreaterThan(0.0, $result->getCostFactor());
        $this->assertLessThanOrEqual(1.0, $result->getCostFactor());
    }

    public function test_it_calculates_error_penalty_from_snapshots(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

        // Create snapshots with error statuses
        Snapshot::create([
            'entity_id' => $entity->id,
            'scraping_status' => ScrapingStatus::SUCCESS,
        ]);
        Snapshot::create([
            'entity_id' => $entity->id,
            'scraping_status' => ScrapingStatus::FAILED,
        ]);
        Snapshot::create([
            'entity_id' => $entity->id,
            'scraping_status' => ScrapingStatus::TIMEOUT,
        ]);

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
                                'value_boost' => 0.5,
                                'priority' => 0.5,
                                'urgency' => 0.5,
                                'next_scrape_at_hours' => 24,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->evaluate($entity);

        // 2 errors out of 3 snapshots = 0.667 error rate
        $this->assertEqualsWithDelta(0.667, $result->getErrorPenalty(), 0.01);
    }

    public function test_it_uses_default_change_boost_when_no_snapshots(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

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
                                'value_boost' => 0.5,
                                'priority' => 0.5,
                                'urgency' => 0.5,
                                'next_scrape_at_hours' => 24,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->evaluate($entity);

        // Default change boost when no snapshots
        $this->assertEquals(0.5, $result->getChangeBoost());
    }

    public function test_it_uses_default_cost_factor_when_no_snapshots(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

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
                                'value_boost' => 0.5,
                                'priority' => 0.5,
                                'urgency' => 0.5,
                                'next_scrape_at_hours' => 24,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->evaluate($entity);

        // Default cost factor when no snapshots
        $this->assertEquals(0.5, $result->getCostFactor());
    }

    public function test_it_uses_default_error_penalty_when_no_snapshots(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

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
                                'value_boost' => 0.5,
                                'priority' => 0.5,
                                'urgency' => 0.5,
                                'next_scrape_at_hours' => 24,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->evaluate($entity);

        // Default error penalty when no snapshots
        $this->assertEquals(0.0, $result->getErrorPenalty());
    }

    public function test_it_uses_json_schema_in_response_format(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

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
                                'value_boost' => 0.5,
                                'priority' => 0.5,
                                'urgency' => 0.5,
                                'next_scrape_at_hours' => 24,
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
                    if ($format === null || ($format['type'] ?? null) !== 'json_schema') {
                        return false;
                    }
                    $jsonSchema = $format['json_schema'] ?? null;
                    return $jsonSchema !== null
                        && ($jsonSchema['name'] ?? null) === 'scrape_policy_evaluation'
                        && ($jsonSchema['strict'] ?? null) === true
                        && isset($jsonSchema['schema']);
                })
            )
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->evaluate($entity);

        $this->assertInstanceOf(PolicyResult::class, $result);
    }

    public function test_it_uses_custom_model_from_config(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

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
                                'value_boost' => 0.5,
                                'priority' => 0.5,
                                'urgency' => 0.5,
                                'next_scrape_at_hours' => 24,
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

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o']);

        $result = $driver->evaluate($entity);

        $this->assertInstanceOf(PolicyResult::class, $result);
    }

    public function test_it_throws_exception_when_openai_api_fails(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andThrow(new \Exception('API connection failed'));

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to evaluate scraping policy with OpenAI');

        $driver->evaluate($entity);
    }

    public function test_it_throws_exception_when_openai_returns_empty_response(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockResponse = ResponseObject::fromArray([
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI returned empty policy evaluation response');

        $driver->evaluate($entity);
    }

    public function test_it_throws_exception_when_response_contains_refusal(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

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
                            'refusal' => 'I cannot evaluate this policy.',
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI refused to evaluate the scraping policy');

        $driver->evaluate($entity);
    }

    public function test_it_throws_exception_for_invalid_json_response(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

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

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse policy evaluation response as JSON');

        $driver->evaluate($entity);
    }

    public function test_it_includes_calculated_factors_in_prompt(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

        // Create snapshots to generate calculated factors
        Snapshot::create([
            'entity_id' => $entity->id,
            'content_change_percentage' => 50.0,
            'cost' => 2.0,
            'scraping_status' => ScrapingStatus::SUCCESS,
        ]);

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
                                'value_boost' => 0.5,
                                'priority' => 0.5,
                                'urgency' => 0.5,
                                'next_scrape_at_hours' => 24,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->with(
                Mockery::on(function ($input) {
                    $inputArray = $input->toArray();
                    $text = $inputArray[0]['content'][0]['text'] ?? '';
                    // Verify calculated factors are included in the prompt
                    return str_contains($text, 'change_boost')
                        && str_contains($text, 'cost_factor')
                        && str_contains($text, 'error_penalty')
                        && str_contains($text, 'Calculated Factors');
                }),
                Mockery::type(\App\Contracts\OpenAI\ResponseOptions::class)
            )
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->evaluate($entity);

        $this->assertInstanceOf(PolicyResult::class, $result);
    }

    public function test_it_handles_entity_with_current_snapshot(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

        $currentSnapshot = Snapshot::create([
            'entity_id' => $entity->id,
            'version' => 1,
            'content_length' => 10000,
            'structured_data_count' => 5,
            'media_count' => 3,
            'link_count' => 10,
            'cost' => 1.5,
        ]);

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
                                'value_boost' => 0.5,
                                'priority' => 0.5,
                                'urgency' => 0.5,
                                'next_scrape_at_hours' => 24,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->evaluate($entity);

        $this->assertInstanceOf(PolicyResult::class, $result);
    }

    public function test_it_handles_different_next_scrape_at_hours(): void
    {
        $source = Source::create(['base_url' => 'https://example-' . uniqid() . '.com']);
        $entity = Entity::create([
            'source_id' => $source->id,
            'url' => 'https://example.com/article',
            'url_hash' => sha1('https://example.com/article'),
        ]);

        $testCases = [
            ['hours' => 1, 'description' => 'breaking news'],
            ['hours' => 24, 'description' => 'daily news'],
            ['hours' => 168, 'description' => 'weekly content'],
            ['hours' => 720, 'description' => 'monthly content'],
        ];

        foreach ($testCases as $testCase) {
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
                                    'value_boost' => 0.5,
                                    'priority' => 0.5,
                                    'urgency' => 0.5,
                                    'next_scrape_at_hours' => $testCase['hours'],
                                ]),
                            ],
                        ],
                    ],
                ],
            ]);

            $mockOpenAIClient->shouldReceive('createResponse')
                ->once()
                ->andReturn($mockResponse);

            $driver = new OpenAIScrapePolicyEngineDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);
            $baseTime = Carbon::now();

            $result = $driver->evaluate($entity, $baseTime);

            $expectedTime = $baseTime->copy()->addHours($testCase['hours']);
            $this->assertEquals(
                $expectedTime->format('Y-m-d H:i:s'),
                $result->getNextScrapeAt()->format('Y-m-d H:i:s'),
                "Failed for {$testCase['description']}"
            );
        }
    }
}
