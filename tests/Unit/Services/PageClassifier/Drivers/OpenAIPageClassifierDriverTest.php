<?php

namespace Tests\Unit\Services\PageClassifier\Drivers;

use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response as ResponseObject;
use App\Contracts\PageClassifier\ClassificationResult;
use App\Enums\ContentType;
use App\Enums\PageType;
use App\Enums\Temporal;
use App\Services\PageClassifier\Drivers\OpenAIPageClassifierDriver;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OpenAIPageClassifierDriverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_classifies_a_page_successfully(): void
    {
        $html = '<html><body><h1>Product Page</h1><p>Buy this amazing product!</p></body></html>';

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
                                'content_type' => 'product',
                                'page_type' => 'detail',
                                'temporal' => 'evergreen',
                                'tags' => ['product', 'shopping', 'ecommerce'],
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->classify($html);

        $this->assertInstanceOf(ClassificationResult::class, $result);
        $this->assertEquals(ContentType::PRODUCT, $result->getContentType());
        $this->assertEquals(PageType::DETAIL, $result->getPageType());
        $this->assertEquals(Temporal::EVERGREEN, $result->getTemporal());
        $this->assertEquals(['product', 'shopping', 'ecommerce'], $result->getTags());
    }

    public function test_it_classifies_an_article_page(): void
    {
        $html = '<html><body><article><h1>Breaking News</h1><p>Latest updates on current events.</p></article></body></html>';

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
                                'content_type' => 'article',
                                'page_type' => 'detail',
                                'temporal' => 'breaking',
                                'tags' => ['news', 'breaking', 'current_events', 'updates'],
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->classify($html);

        $this->assertEquals(ContentType::ARTICLE, $result->getContentType());
        $this->assertEquals(PageType::DETAIL, $result->getPageType());
        $this->assertEquals(Temporal::BREAKING, $result->getTemporal());
        $this->assertCount(4, $result->getTags());
    }

    public function test_it_classifies_a_listing_page(): void
    {
        $html = '<html><body><ul><li>Item 1</li><li>Item 2</li><li>Item 3</li></ul></body></html>';

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
                                'content_type' => 'product',
                                'page_type' => 'listing',
                                'temporal' => 'evergreen',
                                'tags' => ['products', 'catalog', 'list'],
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->classify($html);

        $this->assertEquals(PageType::LISTING, $result->getPageType());
    }

    public function test_it_handles_all_content_types(): void
    {
        $contentTypes = [
            ContentType::ARTICLE,
            ContentType::PRODUCT,
            ContentType::JOB_POSTING,
            ContentType::EVENT,
            ContentType::REVIEW,
        ];

        foreach ($contentTypes as $contentType) {
            $html = "<html><body><h1>{$contentType->value} Page</h1></body></html>";

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
                                    'content_type' => $contentType->value,
                                    'page_type' => 'detail',
                                    'temporal' => 'evergreen',
                                    'tags' => ['test', 'tag1', 'tag2'],
                                ]),
                            ],
                        ],
                    ],
                ],
            ]);

            $mockOpenAIClient->shouldReceive('createResponse')
                ->once()
                ->andReturn($mockResponse);

            $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

            $result = $driver->classify($html);

            $this->assertEquals($contentType, $result->getContentType());
        }
    }

    public function test_it_handles_all_temporal_types(): void
    {
        $temporalTypes = [
            Temporal::EVERGREEN,
            Temporal::BREAKING,
            Temporal::SEASONAL,
            Temporal::TRENDING,
            Temporal::TOPICAL,
        ];

        foreach ($temporalTypes as $temporal) {
            $html = "<html><body><h1>Content</h1></body></html>";

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
                                    'content_type' => 'article',
                                    'page_type' => 'detail',
                                    'temporal' => $temporal->value,
                                    'tags' => ['test', 'tag1', 'tag2'],
                                ]),
                            ],
                        ],
                    ],
                ],
            ]);

            $mockOpenAIClient->shouldReceive('createResponse')
                ->once()
                ->andReturn($mockResponse);

            $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

            $result = $driver->classify($html);

            $this->assertEquals($temporal, $result->getTemporal());
        }
    }

    public function test_it_prepares_html_by_removing_scripts_and_styles(): void
    {
        $html = '<html><head><style>body { color: red; }</style><script>alert("test");</script></head><body><h1>Content</h1></body></html>';

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
                                'content_type' => 'article',
                                'page_type' => 'detail',
                                'temporal' => 'evergreen',
                                'tags' => ['test', 'tag1', 'tag2'],
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
                    // Verify scripts and styles are removed
                    return !str_contains($text, '<script>') && !str_contains($text, '<style>');
                }),
                Mockery::type(\App\Contracts\OpenAI\ResponseOptions::class)
            )
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->classify($html);

        $this->assertInstanceOf(ClassificationResult::class, $result);
    }

    public function test_it_truncates_large_html_content(): void
    {
        $largeHtml = '<html><body>'.str_repeat('<p>Content paragraph</p>', 10000).'</body></html>';

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
                                'content_type' => 'article',
                                'page_type' => 'detail',
                                'temporal' => 'evergreen',
                                'tags' => ['test', 'tag1', 'tag2'],
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
                    // Verify HTML is truncated (should be less than original)
                    return strlen($text) < 500000; // Original would be much larger
                }),
                Mockery::type(\App\Contracts\OpenAI\ResponseOptions::class)
            )
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,[
            'model' => 'gpt-4o-mini',
            'max_html_length' => 50000,
        ]);

        $result = $driver->classify($largeHtml);

        $this->assertInstanceOf(ClassificationResult::class, $result);
    }

    public function test_it_uses_json_schema_in_response_format(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';

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
                                'content_type' => 'article',
                                'page_type' => 'detail',
                                'temporal' => 'evergreen',
                                'tags' => ['test', 'tag1', 'tag2'],
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
                    return $format !== null
                        && ($format['type'] ?? null) === 'json_schema'
                        && ($format['name'] ?? null) === 'page_classification'
                        && ($format['strict'] ?? null) === true
                        && isset($format['schema']);
                })
            )
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->classify($html);

        $this->assertInstanceOf(ClassificationResult::class, $result);
    }

    public function test_it_throws_exception_when_openai_api_fails(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';

        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andThrow(new \Exception('API connection failed'));

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to classify page with OpenAI');

        $driver->classify($html);
    }

    public function test_it_throws_exception_when_openai_returns_empty_response(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';

        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockResponse = ResponseObject::fromArray([
            'id' => 'resp_123',
            'status' => 'completed',
            'output' => [],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI returned empty classification response');

        $driver->classify($html);
    }

    public function test_it_throws_exception_when_response_contains_refusal(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';

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
                            'refusal' => 'I cannot classify this content.',
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI refused to classify the page');

        $driver->classify($html);
    }

    public function test_it_throws_exception_for_invalid_json_response(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';

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

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse classification response as JSON');

        $driver->classify($html);
    }

    public function test_it_throws_exception_for_invalid_content_type(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';

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
                                'content_type' => 'invalid_type',
                                'page_type' => 'detail',
                                'temporal' => 'evergreen',
                                'tags' => ['test', 'tag1', 'tag2'],
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid content_type value');

        $driver->classify($html);
    }

    public function test_it_throws_exception_for_invalid_page_type(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';

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
                                'content_type' => 'article',
                                'page_type' => 'invalid_type',
                                'temporal' => 'evergreen',
                                'tags' => ['test', 'tag1', 'tag2'],
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid page_type value');

        $driver->classify($html);
    }

    public function test_it_throws_exception_for_invalid_temporal(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';

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
                                'content_type' => 'article',
                                'page_type' => 'detail',
                                'temporal' => 'invalid_temporal',
                                'tags' => ['test', 'tag1', 'tag2'],
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid temporal value');

        $driver->classify($html);
    }

    public function test_it_uses_custom_model_from_config(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';

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
                                'content_type' => 'article',
                                'page_type' => 'detail',
                                'temporal' => 'evergreen',
                                'tags' => ['test', 'tag1', 'tag2'],
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

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o']);

        $result = $driver->classify($html);

        $this->assertInstanceOf(ClassificationResult::class, $result);
    }

    public function test_it_handles_tags_correctly(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';

        $tags = ['tag1', 'tag2', 'tag3', 'tag4', 'tag5'];

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
                                'content_type' => 'article',
                                'page_type' => 'detail',
                                'temporal' => 'evergreen',
                                'tags' => $tags,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIPageClassifierDriver($mockOpenAIClient,['model' => 'gpt-4o-mini']);

        $result = $driver->classify($html);

        $this->assertEquals($tags, $result->getTags());
        $this->assertCount(5, $result->getTags());
    }
}
