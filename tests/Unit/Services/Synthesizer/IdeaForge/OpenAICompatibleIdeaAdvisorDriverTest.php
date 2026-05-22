<?php

namespace Tests\Unit\Services\Synthesizer\IdeaForge;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;
use App\Enums\IntentType;
use App\Enums\Temporal;
use App\Contracts\OpenAI\OpenAIClient;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAICompatibleIdeaAdvisorDriver;
use Mockery;
use Tests\TestCase;

class OpenAICompatibleIdeaAdvisorDriverTest extends TestCase
{
    public function test_suggest_temporal_parses_chat_completion_response(): void
    {
        $payload = [
            'temporal_suggestions' => [
                [
                    'temporal' => 'trending',
                    'confidence' => 0.9,
                    'reason' => 'Context implies current interest.',
                ],
            ],
        ];

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')
            ->once()
            ->andReturn($this->responseFromJson($payload));

        $driver = $this->makeDriver($client);
        $suggestions = $driver->suggestTemporal(
            'client-a',
            (new SemanticContext)->set('article_context', 'Article context', 'Latest market moves this week')
        );

        $this->assertCount(1, $suggestions);
        $this->assertInstanceOf(TemporalSuggestion::class, $suggestions[0]);
        $this->assertSame(Temporal::TRENDING, $suggestions[0]->getTemporal());
        $this->assertSame(0.9, $suggestions[0]->getConfidence());
    }

    public function test_brainstorm_returns_ideas_from_chat_completion_response(): void
    {
        $payload = [
            'ideas' => [
                [
                    'title' => 'Weekly digest',
                    'description' => 'A concise recap of the week.',
                    'temporal' => 'topical',
                    'intent_type' => 'informational',
                    'confidence' => 0.82,
                    'reason' => 'Matches informational intent.',
                ],
            ],
        ];

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')
            ->once()
            ->andReturn($this->responseFromJson($payload));

        $driver = $this->makeDriver($client);

        $temporal = [new TemporalSuggestion(Temporal::TOPICAL, 0.7, 'test')];
        $intentTypes = [new IntentTypeSuggestion(IntentType::INFORMATIONAL, 0.8, 'test')];

        $ideas = $driver->brainstorm(
            $temporal,
            $intentTypes,
            (new SemanticContext)->set('article_context', 'Article context', 'Newsletter context'),
            3
        );

        $this->assertCount(1, $ideas);
        $this->assertInstanceOf(Idea::class, $ideas[0]);
        $this->assertSame('Weekly digest', $ideas[0]->getIntent()->getTitle());
        $this->assertSame(Temporal::TOPICAL, $ideas[0]->getIntent()->getTemporal());
    }

    public function test_manager_resolves_openai_compatible_driver(): void
    {
        $advisor = $this->app->make(\App\Services\Synthesizer\IdeaForge\IdeaAdvisor\IdeaAdvisorManager::class)
            ->driver('openai_compatible');

        $this->assertInstanceOf(OpenAICompatibleIdeaAdvisorDriver::class, $advisor);
        $this->assertSame('openai-compatible-idea-advisor', $advisor->getIdentifier());
    }

    protected function makeDriver(OpenAIClient $client): OpenAICompatibleIdeaAdvisorDriver
    {
        $driver = new OpenAICompatibleIdeaAdvisorDriver(['model' => 'test-model']);
        $ref = new \ReflectionProperty(OpenAICompatibleIdeaAdvisorDriver::class, 'openAIClient');
        $ref->setAccessible(true);
        $ref->setValue($driver, $client);

        return $driver;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function responseFromJson(array $data): \App\Contracts\OpenAI\Response
    {
        return \App\Contracts\OpenAI\Response::fromArray([
            'id' => 'test',
            'created_at' => time(),
            'status' => 'completed',
            'model' => 'test-model',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => json_encode($data, JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ],
        ]);
    }
}
