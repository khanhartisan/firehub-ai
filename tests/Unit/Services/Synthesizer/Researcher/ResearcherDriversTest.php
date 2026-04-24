<?php

namespace Tests\Unit\Services\Synthesizer\Researcher;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\ConflictedPoints;
use App\Contracts\Synthesizer\Researcher\ConsolidationResult;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Services\Synthesizer\Researcher\Drivers\BasicResearcherDriver;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use Tests\TestCase;

class ResearcherDriversTest extends TestCase
{
    public function test_basic_researcher_extracts_points_with_headlines_and_relevance(): void
    {
        $driver = new BasicResearcherDriver;
        $idea = new Idea($this->makeIntent(), 0.78, 'Fit for operators');

        $content = <<<'TEXT'
AI copilots are being adopted quickly across product teams. Teams report faster prototyping cycles and better documentation consistency.

Costs remain a concern for scale deployments. Leaders are introducing usage caps and team-level governance policies to control spend.

Security review now appears earlier in procurement checklists. Buyers ask for clearer data handling and retention controls before rollout.
TEXT;

        $result = $driver->extractIdeaPoints($idea, $content);
        $rows = $result;

        $this->assertCount(3, $rows);
        $this->assertInstanceOf(RelevantPoint::class, $rows[0]);
        $this->assertNotNull($rows[0]->getHeadline());
        $this->assertNotEmpty($rows[0]->getEvidences());
        $this->assertNotNull($rows[0]->getRationale());
        $this->assertGreaterThanOrEqual(0.6, (float) $rows[2]->getRelevance());
    }

    public function test_basic_researcher_returns_empty_collection_for_blank_content(): void
    {
        $driver = new BasicResearcherDriver;
        $idea = new Idea($this->makeIntent(), 0.61, 'Test');

        $result = $driver->extractIdeaPoints($idea, " \n\t ");

        $this->assertSame([], $result);
    }

    public function test_basic_researcher_consolidate_returns_result_with_same_points_and_no_conflicts(): void
    {
        $driver = new BasicResearcherDriver;
        $idea = new Idea($this->makeIntent(), 0.8, 'Consolidation test');
        $input = [
            (new RelevantPoint)
                ->setHeadline('Point A')
                ->setDescription('Description A')
                ->setEvidences(['Evidence A'])
                ->setRationale('Rationale A')
                ->setRelevance(0.9),
            (new RelevantPoint)
                ->setHeadline('Point B')
                ->setDescription('Description B')
                ->setEvidences(['Evidence B'])
                ->setRationale('Rationale B')
                ->setRelevance(0.7),
        ];

        $result = $driver->consolidateIdeaPoints($idea, $input);

        $this->assertInstanceOf(ConsolidationResult::class, $result);
        $this->assertCount(2, $result->getPoints());
        $this->assertSame('Point A', $result->getPoints()[0]->getHeadline());
        $this->assertSame('Point B', $result->getPoints()[1]->getHeadline());
        $this->assertSame([], $result->getConflicts());
    }

    public function test_basic_researcher_resolve_conflicted_points_uses_provided_facts(): void
    {
        $driver = new BasicResearcherDriver;
        $idea = new Idea($this->makeIntent(), 0.8, 'Resolve conflict test');
        $conflictedPoints = (new ConflictedPoints)
            ->setRationale('Claims conflict on growth rate.')
            ->setPoints([
                (new RelevantPoint)
                    ->setHeadline('Growth is 20%')
                    ->setDescription('Source A indicates 20% growth.')
                    ->setEvidences(['Source A'])
                    ->setRationale('Based on Source A')
                    ->setRelevance(0.7),
            ]);

        $resolved = $driver->resolveIdeaConflictedPoints($idea, $conflictedPoints, [
            ['fact' => 'Verified growth is 12% YoY.'],
        ]);

        $this->assertInstanceOf(RelevantPoint::class, $resolved);
        $this->assertSame(['Verified growth is 12% YoY.'], $resolved->getEvidences());
        $this->assertSame('Resolved from conflicted points using provided verified facts.', $resolved->getRationale());
    }

    protected function makeIntent(): Intent
    {
        return (new Intent)
            ->setTitle('AI operations benchmarks')
            ->setDescription('How operational teams evaluate AI tooling in practice.')
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::EVERGREEN)
            ->setTypes([IntentType::INFORMATIONAL]);
    }
}
