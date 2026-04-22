<?php

namespace Tests\Unit\Services\Synthesizer\IdeaForge;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;
use App\Enums\IntentType;
use App\Enums\Temporal;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAIIdeaAdvisorDriver;
use Mockery;
use Tests\TestCase;

class OpenAIIdeaAdvisorDriverTest extends TestCase
{
    public function test_suggest_temporal_parses_structured_response(): void
    {
        $payload = json_encode([
            'temporal_suggestions' => [
                [
                    'temporal' => 'trending',
                    'confidence' => 0.9,
                    'reason' => 'Context implies current interest.',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
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
                            'text' => $payload,
                        ],
                    ],
                ],
            ],
        ]);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($response);

        $driver = new OpenAIIdeaAdvisorDriver($client, ['model' => 'gpt-4o-mini']);
        $suggestions = $driver->suggestTemporal(
            'client-a',
            (new SemanticContext)->set('article_context', 'Article context', 'Latest market moves this week')
        );

        $this->assertCount(1, $suggestions);
        $this->assertInstanceOf(TemporalSuggestion::class, $suggestions[0]);
        $this->assertSame(Temporal::TRENDING, $suggestions[0]->getTemporal());
        $this->assertSame(0.9, $suggestions[0]->getConfidence());
    }

    public function test_brainstorm_returns_ideas_from_structured_response(): void
    {
        $payload = json_encode([
            'ideas' => [
                [
                    'title' => 'Weekly digest',
                    'description' => 'A concise recap of the week.',
                    'temporal' => 'topical',
                    'intent_type' => 1,
                    'confidence' => 0.82,
                    'reason' => 'Matches informational intent.',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_2',
            'created_at' => time(),
            'status' => 'completed',
            'model' => 'gpt-4o-mini',
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => $payload,
                        ],
                    ],
                ],
            ],
        ]);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($response);

        $driver = new OpenAIIdeaAdvisorDriver($client, []);

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
}
