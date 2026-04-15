<?php

namespace Tests\Unit\Services\Synthesizer\IdeaForge;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\BasicIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\BasicIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\BasicIdeaPickerDriver;
use Tests\TestCase;

class IdeaForgeDriversTest extends TestCase
{
    public function test_idea_advisor_returns_sorted_suggestions_and_brainstorms_with_limit(): void
    {
        $advisor = new BasicIdeaAdvisorDriver;
        $context = 'latest pricing updates today near me';

        $temporal = $advisor->suggestTemporal('client-1', $context);
        $intentTypes = $advisor->suggestIntentTypes('client-1', $context);
        $ideas = $advisor->brainstorm($temporal, $intentTypes, $context, 2);

        $this->assertNotEmpty($temporal);
        $this->assertNotEmpty($intentTypes);
        $this->assertCount(2, $ideas);
        $this->assertInstanceOf(TemporalSuggestion::class, $temporal[0]);
        $this->assertInstanceOf(IntentTypeSuggestion::class, $intentTypes[0]);
        $this->assertInstanceOf(Idea::class, $ideas[0]);
        $this->assertSame(Language::EN, $ideas[0]->getIntent()->getLanguage());
    }

    public function test_idea_auditor_generates_score_highlights_and_concerns(): void
    {
        $auditor = new BasicIdeaAuditorDriver;
        $idea = new Idea($this->makeIntent(description: ''), 0.4, 'Low confidence draft');

        $report = $auditor->audit($idea);

        $this->assertInstanceOf(IdeaAuditReport::class, $report);
        $this->assertSame(0.4, $report->getScore());
        $this->assertNotEmpty($report->getHighlights());
        $this->assertNotEmpty($report->getConcerns());
    }

    public function test_idea_picker_returns_highest_scored_reports_and_null_when_empty(): void
    {
        $picker = new BasicIdeaPickerDriver;

        $low = new IdeaAuditReport(new Idea($this->makeIntent('Low'), 0.2), 0.2);
        $high = new IdeaAuditReport(new Idea($this->makeIntent('High'), 0.9), 0.9);

        $picked = $picker->pick([$low, $high], 'context', 1);
        $none = $picker->pick([], 'context', 1);

        $this->assertNotNull($picked);
        $this->assertCount(1, $picked);
        $this->assertSame(0.9, $picked[0]->getScore());
        $this->assertNull($none);
    }

    protected function makeIntent(string $description = 'Description'): Intent
    {
        return (new Intent)
            ->setTitle('Intent title')
            ->setDescription($description)
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::TOPICAL)
            ->setTypes([IntentType::INFORMATIONAL]);
    }
}
