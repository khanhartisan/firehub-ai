<?php

namespace Tests\Unit\Contracts\Synthesizer;

use App\Contracts\CommonData\Point;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\ConsolidatedIdeaPoint;
use App\Contracts\Synthesizer\Researcher\IdeaPoint;
use App\Contracts\Synthesizer\Researcher\IdeaPoints;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use Tests\TestCase;

class ResearcherDataObjectsTest extends TestCase
{
    public function test_idea_points_to_array_omits_redundant_idea_in_nested_rows(): void
    {
        $idea = new Idea($this->makeIntent(), 0.81, 'Aligned with content direction');
        $point = (new Point)
            ->setHeadline('High demand from practitioners')
            ->setDescription('Multiple studies show consistent adoption growth.')
            ->setEvidences(['Survey reports 28% YoY growth']);

        $ideaPoints = new IdeaPoints($idea, [
            (new IdeaPoint($idea, $point, 0.94))
                ->setRationale('Growth data aligns with the idea direction.'),
        ]);

        $payload = $ideaPoints->toArray();

        $this->assertArrayHasKey('idea', $payload);
        $this->assertArrayHasKey('idea_points', $payload);
        $this->assertCount(1, $payload['idea_points']);
        $this->assertArrayNotHasKey('idea', $payload['idea_points'][0]);
        $this->assertSame(0.94, $payload['idea_points'][0]['relevance']);
        $this->assertSame('Growth data aligns with the idea direction.', $payload['idea_points'][0]['rationale']);
        $this->assertSame('High demand from practitioners', $payload['idea_points'][0]['point']['headline']);
    }

    public function test_idea_points_from_array_backfills_missing_nested_idea(): void
    {
        $idea = new Idea($this->makeIntent(), 0.73, 'Clear informational fit');
        $payload = [
            'idea' => $idea->toArray(),
            'idea_points' => [
                [
                    'point' => [
                        'headline' => 'Growing budget allocation',
                        'description' => 'Teams increase spend for automation tools.',
                        'evidences' => ['Budget line items increased in annual reports'],
                    ],
                    'rationale' => 'Budget growth indicates stronger investment confidence.',
                    'relevance' => 0.88,
                ],
            ],
        ];

        $restored = IdeaPoints::fromArray($payload);

        $this->assertSame($idea->getIdentifier(), $restored->getIdea()->getIdentifier());
        $this->assertCount(1, $restored->getIdeaPoints());
        $this->assertSame($idea->getIdentifier(), $restored->getIdeaPoints()[0]->getIdea()->getIdentifier());
        $this->assertSame(0.88, $restored->getIdeaPoints()[0]->getRelevance());
        $this->assertSame('Budget growth indicates stronger investment confidence.', $restored->getIdeaPoints()[0]->getRationale());
        $this->assertSame('Growing budget allocation', $restored->getIdeaPoints()[0]->getPoint()->getHeadline());
    }

    public function test_consolidated_idea_point_hydrates_and_serializes_conflicts(): void
    {
        $idea = new Idea($this->makeIntent(), 0.6, 'Consolidated insight');
        $payload = [
            'idea' => $idea->toArray(),
            'point' => [
                'headline' => 'Headline',
                'description' => 'Description',
                'evidences' => ['Evidence'],
            ],
            'relevance' => 0.77,
            'rationale' => 'Consolidated rationale',
            'conflicts' => [
                [
                    'facts' => [
                        ['fact' => 'Claim A'],
                        ['fact' => 'Claim B'],
                    ],
                    'rationale' => 'Claims disagree on totals.',
                ],
            ],
        ];

        $consolidated = ConsolidatedIdeaPoint::fromArray($payload);

        $this->assertCount(1, $consolidated->getConflicts());
        $this->assertSame('Claim A', $consolidated->getConflicts()[0]->getFacts()[0]->getFact());
        $this->assertSame('Claims disagree on totals.', $consolidated->getConflicts()[0]->getRationale());

        $serialized = $consolidated->toArray();
        $this->assertArrayHasKey('conflicts', $serialized);
        $this->assertSame('Claim B', $serialized['conflicts'][0]['facts'][1]['fact']);
    }

    protected function makeIntent(): Intent
    {
        return (new Intent)
            ->setTitle('AI workflow automation')
            ->setDescription('How teams adopt AI to improve output quality and speed.')
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::EVERGREEN)
            ->setTypes([IntentType::INFORMATIONAL, IntentType::COMMERCIAL]);
    }
}
