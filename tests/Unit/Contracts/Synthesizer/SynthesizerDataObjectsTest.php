<?php

namespace Tests\Unit\Contracts\Synthesizer;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\Author\Draft;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Models\Article;
use Tests\TestCase;

class SynthesizerDataObjectsTest extends TestCase
{
    public function test_idea_round_trip_serialization(): void
    {
        $idea = new Idea(
            intent: $this->makeIntent(),
            confidence: 0.82,
            reason: 'Strong audience fit.',
        );

        $payload = $idea->toArray();
        $restored = Idea::fromArray($payload);

        $this->assertSame($idea->getIdentifier(), $restored->getIdentifier());
        $this->assertSame(0.82, $restored->getConfidence());
        $this->assertSame('Strong audience fit.', $restored->getReason());
        $this->assertSame('AI tooling guide', $restored->getIntent()->getTitle());
    }

    public function test_idea_audit_report_round_trip_serialization(): void
    {
        $report = new IdeaAuditReport(
            idea: new Idea($this->makeIntent(), 0.6, 'Good baseline'),
            score: 0.74,
            highlights: ['Clear angle', 'Good intent match'],
            concerns: ['Needs more examples'],
        );

        $payload = $report->toArray();
        $restored = IdeaAuditReport::fromArray($payload);

        $this->assertSame(0.74, $restored->getScore());
        $this->assertSame(['Clear angle', 'Good intent match'], $restored->getHighlights());
        $this->assertSame(['Needs more examples'], $restored->getConcerns());
        $this->assertSame($report->getIdea()->getIdentifier(), $restored->getIdea()->getIdentifier());
    }

    public function test_temporal_and_intent_type_suggestions_round_trip_serialization(): void
    {
        $temporalSuggestion = new TemporalSuggestion(Temporal::EVERGREEN, 0.91, 'Long-tail topic');
        $restoredTemporal = TemporalSuggestion::fromArray($temporalSuggestion->toArray());
        $this->assertSame(Temporal::EVERGREEN, $restoredTemporal->getTemporal());
        $this->assertSame(0.91, $restoredTemporal->getConfidence());
        $this->assertSame('Long-tail topic', $restoredTemporal->getReason());

        $intentSuggestion = new IntentTypeSuggestion(IntentType::INFORMATIONAL, 0.77, 'Research intent');
        $restoredIntent = IntentTypeSuggestion::fromArray($intentSuggestion->toArray());
        $this->assertSame(IntentType::INFORMATIONAL, $restoredIntent->getIntentType());
        $this->assertSame(0.77, $restoredIntent->getConfidence());
        $this->assertSame('Research intent', $restoredIntent->getReason());
    }

    public function test_brief_outline_and_draft_round_trip_serialization(): void
    {
        $brief = (new Brief)
            ->setTemporal(Temporal::TRENDING)
            ->setTitle('AI weekly roundup')
            ->setDescription('Top changes this week.')
            ->setInstructions(['Use concise bullets', 'Prioritize new developments'])
            ->setReferencePageIds([]);

        $restoredBrief = Brief::fromArray($brief->toArray());
        $this->assertSame(Temporal::TRENDING, $restoredBrief->getTemporal());
        $this->assertSame('AI weekly roundup', $restoredBrief->getTitle());
        $this->assertEmpty($restoredBrief->getReferencePages());

        $outlineItem = (new OutlineItem)
            ->setHeading('Key updates')
            ->setBrief('Major model and pricing updates.')
            ->setInstructions(['Keep to five bullets']);

        $outline = (new Outline)
            ->setTitle('Weekly structure')
            ->setItems([$outlineItem]);

        $restoredOutline = Outline::fromArray($outline->toArray());
        $this->assertSame('Weekly structure', $restoredOutline->getTitle());
        $this->assertCount(1, $restoredOutline->getItems());
        $this->assertSame('Key updates', $restoredOutline->getItems()[0]->getHeading());

        $draft = (new Draft)
            ->setTitle('Draft title')
            ->setExcerpt('Draft excerpt')
            ->setBodyMarkdown('## Key updates')
            ->setReferenceFileIds([]);

        $restoredDraft = Draft::fromArray($draft->toArray());
        $this->assertSame('Draft title', $restoredDraft->getTitle());
        $this->assertSame('Draft excerpt', $restoredDraft->getExcerpt());
        $this->assertSame('## Key updates', $restoredDraft->getBodyMarkdown());
        $this->assertEmpty($restoredDraft->getReferenceFiles());
    }

    public function test_idea_uniqueness_report_getters_setters_and_to_array(): void
    {
        $article = new Article(['id' => 'art-1', 'title' => 'Existing article']);

        $report = (new IdeaUniquenessReport)
            ->setClientId('client-1')
            ->setIdeaIdentifier('idea-xyz')
            ->setIsUnique(false)
            ->setSimilarity(0.88)
            ->setSimilarArticles([$article]);

        $this->assertSame('client-1', $report->getClientId());
        $this->assertSame('idea-xyz', $report->getIdeaIdentifier());
        $this->assertFalse($report->getIsUnique());
        $this->assertSame(0.88, $report->getSimilarity());
        $this->assertCount(1, $report->getSimilarArticles());

        $payload = $report->toArray();
        $this->assertSame('client-1', $payload['client_id']);
        $this->assertSame('idea-xyz', $payload['idea_identifier']);
        $this->assertFalse($payload['is_unique']);
        $this->assertSame(0.88, $payload['similarity']);
        $this->assertCount(1, $payload['similar_articles']);
    }

    protected function makeIntent(): Intent
    {
        return (new Intent)
            ->setTitle('AI tooling guide')
            ->setDescription('How to evaluate AI coding tools.')
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::EVERGREEN)
            ->setTypes([IntentType::INFORMATIONAL, IntentType::COMMERCIAL]);
    }
}
