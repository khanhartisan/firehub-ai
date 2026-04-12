<?php

namespace Tests\Unit\Services\IntentResolver\Drivers;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response as ResponseObject;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Services\IntentResolver\Drivers\OpenAIIntentResolverDriver;
use Mockery;
use Tests\TestCase;

class OpenAIIntentResolverDriverMergeIntentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_merge_intents_returns_merged_intent_when_model_says_merge(): void
    {
        $intent1 = (new Intent)
            ->setTitle('A')
            ->setDescription(str_repeat('x', 120))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

        $intent2 = (new Intent)
            ->setTitle('B')
            ->setDescription(str_repeat('y', 120))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

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
                                'should_merge' => true,
                                'merged_intent' => [
                                    'title' => 'Merged title',
                                    'description' => str_repeat('z', 120),
                                    'language' => 'en',
                                    'types' => [IntentType::INFORMATIONAL->value],
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

        $driver = new OpenAIIntentResolverDriver($mockOpenAIClient, ['model' => 'gpt-4o-mini']);

        $merged = $driver->mergeIntents($intent1, $intent2);

        $this->assertNotNull($merged);
        $this->assertSame('Merged title', $merged->getTitle());
    }

    public function test_merge_intents_returns_null_when_model_says_distinct(): void
    {
        $intent1 = (new Intent)
            ->setTitle('A')
            ->setDescription(str_repeat('x', 120))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::INFORMATIONAL]);

        $intent2 = (new Intent)
            ->setTitle('B')
            ->setDescription(str_repeat('y', 120))
            ->setLanguage(Language::EN)
            ->setTypes([IntentType::TRANSACTIONAL]);

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
                                'should_merge' => false,
                                'merged_intent' => null,
                            ]),
                        ],
                    ],
                ],
            ],
        ]);

        $mockOpenAIClient->shouldReceive('createResponse')
            ->once()
            ->andReturn($mockResponse);

        $driver = new OpenAIIntentResolverDriver($mockOpenAIClient, ['model' => 'gpt-4o-mini']);

        $this->assertNull($driver->mergeIntents($intent1, $intent2));
    }
}
