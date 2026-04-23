<?php

namespace Tests\Unit\Services\Synthesizer\Researcher;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\IdeaForge\Idea;
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

        $result = $driver->extractPoints($idea, $content);
        $rows = $result->getIdeaPoints();

        $this->assertSame($idea, $result->getIdea());
        $this->assertCount(3, $rows);
        $this->assertSame($idea, $rows[0]->getIdea());
        $this->assertNotNull($rows[0]->getPoint()->getHeadline());
        $this->assertNotEmpty($rows[0]->getPoint()->getEvidences());
        $this->assertNotNull($rows[0]->getRationale());
        $this->assertGreaterThanOrEqual(0.6, (float) $rows[2]->getRelevance());
    }

    public function test_basic_researcher_returns_empty_collection_for_blank_content(): void
    {
        $driver = new BasicResearcherDriver;
        $idea = new Idea($this->makeIntent(), 0.61, 'Test');

        $result = $driver->extractPoints($idea, " \n\t ");

        $this->assertSame($idea, $result->getIdea());
        $this->assertSame([], $result->getIdeaPoints());
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
