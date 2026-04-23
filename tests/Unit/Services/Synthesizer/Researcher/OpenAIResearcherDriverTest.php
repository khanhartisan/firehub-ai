<?php

namespace Tests\Unit\Services\Synthesizer\Researcher;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Services\Synthesizer\Researcher\Drivers\OpenAIResearcherDriver;
use Mockery;
use Tests\TestCase;

class OpenAIResearcherDriverTest extends TestCase
{
    public function test_extract_points_parses_structured_response(): void
    {
        $payload = json_encode([
            'points' => [
                [
                    'headline' => 'Adoption is accelerating',
                    'description' => 'Teams report faster prototyping and delivery cycles.',
                    'evidences' => [
                        'Survey shows increased weekly usage.',
                        'Interviewed teams cite shorter iteration loops.',
                    ],
                    'rationale' => 'These outcomes directly support the proposed adoption-focused idea angle.',
                    'relevance' => 0.91,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_research_1',
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

        $driver = new OpenAIResearcherDriver($client, ['model' => 'gpt-4o-mini']);
        $idea = new Idea($this->makeIntent(), 0.7, 'fit');
        $result = $driver->extractPoints($idea, 'Source text');

        $this->assertCount(1, $result->getIdeaPoints());
        $this->assertSame($idea, $result->getIdeaPoints()[0]->getIdea());
        $this->assertSame('Adoption is accelerating', $result->getIdeaPoints()[0]->getPoint()->getHeadline());
        $this->assertSame('These outcomes directly support the proposed adoption-focused idea angle.', $result->getIdeaPoints()[0]->getRationale());
        $this->assertSame(0.91, $result->getIdeaPoints()[0]->getRelevance());
    }

    public function test_extract_points_skips_openai_for_blank_content(): void
    {
        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldNotReceive('createResponse');

        $driver = new OpenAIResearcherDriver($client, []);
        $idea = new Idea($this->makeIntent(), 0.7, 'fit');
        $result = $driver->extractPoints($idea, " \n\t ");

        $this->assertSame([], $result->getIdeaPoints());
    }

    protected function makeIntent(): Intent
    {
        return (new Intent)
            ->setTitle('AI copilots in product teams')
            ->setDescription('How teams adopt copilot tooling in daily workflows.')
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::TOPICAL)
            ->setTypes([IntentType::INFORMATIONAL]);
    }
}
