<?php

namespace Tests\Unit\Services\Synthesizer\Researcher;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\OpenAI\Response;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\ConflictedPoints;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
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
        $result = $driver->extractIdeaPoints($idea, 'Source text');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(RelevantPoint::class, $result[0]);
        $this->assertSame('Adoption is accelerating', $result[0]->getHeadline());
        $this->assertSame(
            'Teams report faster prototyping and delivery cycles.',
            $result[0]->getDescription()
        );
        $this->assertSame(
            ['Survey shows increased weekly usage.', 'Interviewed teams cite shorter iteration loops.'],
            $result[0]->getEvidences()
        );
        $this->assertSame('These outcomes directly support the proposed adoption-focused idea angle.', $result[0]->getRationale());
        $this->assertSame(0.91, $result[0]->getRelevance());
    }

    public function test_extract_points_skips_openai_for_blank_content(): void
    {
        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldNotReceive('createResponse');

        $driver = new OpenAIResearcherDriver($client, []);
        $idea = new Idea($this->makeIntent(), 0.7, 'fit');
        $result = $driver->extractIdeaPoints($idea, " \n\t ");

        $this->assertSame([], $result);
    }

    public function test_consolidate_points_uses_openai_and_maps_result(): void
    {
        $payload = json_encode([
            'points' => [
                [
                    'headline' => 'Adoption keeps rising',
                    'description' => 'Multiple sources indicate continued weekly growth.',
                    'evidences' => ['Usage rose quarter over quarter'],
                    'rationale' => 'Cross-source trend consistency',
                    'relevance' => 0.89,
                ],
            ],
            'conflicts' => [
                [
                    'rationale' => 'Sources disagree on ROI magnitude.',
                    'points' => [
                        [
                            'headline' => 'ROI is 2x',
                            'description' => 'One survey claims approximately 2x impact.',
                            'evidences' => ['Survey A'],
                            'rationale' => 'Vendor-led survey estimate',
                            'relevance' => 0.7,
                        ],
                        [
                            'headline' => 'ROI is 1.2x',
                            'description' => 'Another report shows lower measured gains.',
                            'evidences' => ['Survey B'],
                            'rationale' => 'Independent analyst benchmark',
                            'relevance' => 0.66,
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = Response::fromArray([
            'id' => 'resp_research_consolidate_1',
            'created_at' => time(),
            'status' => 'completed',
            'model' => 'gpt-4o-mini',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => $payload,
                ]],
            ]],
        ]);

        $client = Mockery::mock(OpenAIClient::class);
        $client->shouldReceive('createResponse')->once()->andReturn($response);

        $driver = new OpenAIResearcherDriver($client, ['model' => 'gpt-4o-mini']);
        $idea = new Idea($this->makeIntent(), 0.7, 'fit');
        $input = [
            (new RelevantPoint)
                ->setHeadline('Raw point')
                ->setDescription('Raw description')
                ->setEvidences(['Raw evidence'])
                ->setRationale('Raw rationale')
                ->setRelevance(0.8),
        ];

        $result = $driver->consolidateIdeaPoints($idea, $input);

        $this->assertCount(1, $result->getPoints());
        $this->assertSame('Adoption keeps rising', $result->getPoints()[0]->getHeadline());
        $this->assertCount(1, $result->getConflicts());
        $this->assertInstanceOf(ConflictedPoints::class, $result->getConflicts()[0]);
        $this->assertSame('Sources disagree on ROI magnitude.', $result->getConflicts()[0]->getRationale());
        $this->assertCount(2, $result->getConflicts()[0]->getPoints());
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
