<?php

namespace Tests\Unit\Contracts\Synthesizer;

use App\Contracts\CommonData\AudienceContext;
use App\Contracts\DOM\Article as DOMArticle;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Contracts\Synthesizer\Critic\Rectification;
use App\Contracts\Synthesizer\Writer\Draft;
use App\Contracts\Synthesizer\Writer\IllustrationAnchor;
use App\Contracts\Synthesizer\Writer\RectifiedArticle;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Enums\ContentGoal;
use App\Enums\ContentTone;
use App\Enums\ContentVoice;
use App\Enums\Country;
use App\Enums\IntentType;
use App\Enums\KnowledgeLevel;
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
            ->setAudienceContexts([
                (new AudienceContext)
                    ->setName('Engineering leaders')
                    ->setLanguage(Language::EN),
            ])
            ->setTitle('AI weekly roundup')
            ->setDescription('Top changes this week.')
            ->setGoal(ContentGoal::INFORM)
            ->setVoice(ContentVoice::AUTHORITATIVE)
            ->setTone(ContentTone::OBJECTIVE)
            ->setInstructions(['Use concise bullets', 'Prioritize new developments'])
            ->setReferencePageIds([]);

        $briefPayload = $brief->toArray();
        $this->assertArrayNotHasKey('keywords', $briefPayload);

        $restoredBrief = Brief::fromArray($briefPayload);
        $this->assertSame(Temporal::TRENDING, $restoredBrief->getTemporal());
        $this->assertCount(1, $restoredBrief->getAudienceContexts());
        $this->assertSame('Engineering leaders', $restoredBrief->getAudienceContexts()[0]->getNameValue());
        $this->assertSame('AI weekly roundup', $restoredBrief->getTitle());
        $this->assertSame(ContentGoal::INFORM, $restoredBrief->getGoal());
        $this->assertSame(ContentVoice::AUTHORITATIVE, $restoredBrief->getVoice());
        $this->assertSame(ContentTone::OBJECTIVE, $restoredBrief->getTone());
        $this->assertEmpty($restoredBrief->getReferencePages());

        $legacyPayload = $briefPayload;
        $legacyPayload['keywords'] = ['ai coding'];
        $legacyRestoredBrief = Brief::fromArray($legacyPayload);
        $this->assertSame($restoredBrief->toArray(), $legacyRestoredBrief->toArray());

        $outlineItem = (new OutlineItem)
            ->setPoint(
                (new RelevantPoint)
                    ->setHeadline('Key updates')
                    ->setDescription('Major model and pricing updates.')
                    ->setEvidences(['Keep to five bullets'])
            )
            ->setGuidelines(['Keep to five bullets']);

        $outline = (new Outline)
            ->setTitle('Weekly structure')
            ->setItems([$outlineItem]);

        $restoredOutline = Outline::fromArray($outline->toArray());
        $this->assertSame('Weekly structure', $restoredOutline->getTitle());
        $this->assertCount(1, $restoredOutline->getItems());
        $this->assertSame('Key updates', $restoredOutline->getItems()[0]->getPoint()->getHeadline());
        $this->assertSame(['Keep to five bullets'], $restoredOutline->getItems()[0]->getGuidelines());

        $draft = (new Draft)
            ->setTitle('Draft title')
            ->setExcerpt('Draft excerpt')
            ->setArticle(
                (new DOMArticle)->setChildren([
                    (new Element)->setType(ElementType::H2)->addChild('Key updates'),
                ])
            )
            ->setReferenceFileIds([]);

        $restoredDraft = Draft::fromArray($draft->toArray());
        $this->assertSame('Draft title', $restoredDraft->getTitle());
        $this->assertSame('Draft excerpt', $restoredDraft->getExcerpt());
        $this->assertNotNull($restoredDraft->getArticle());
        $this->assertStringContainsString('<h2>Key updates</h2>', $restoredDraft->getArticle()->toHtml());
        $this->assertEmpty($restoredDraft->getReferenceFiles());
    }

    public function test_rectified_article_round_trip_serialization(): void
    {
        $article = (new DOMArticle)->setChildren([
            (new Element)->setType(ElementType::H2)->addChild('Key updates'),
        ]);

        $rectified = (new RectifiedArticle)
            ->setArticle($article)
            ->setRectifications([
                (new Rectification)
                    ->setReference('abcd')
                    ->setConfidence(0.91)
                    ->setAdjustments(['Expanded the section with supporting detail.']),
            ]);

        $restored = RectifiedArticle::fromArray($rectified->toArray());

        $this->assertNotNull($restored->getArticle());
        $this->assertStringContainsString('<h2>Key updates</h2>', $restored->getArticle()->toHtml());
        $this->assertCount(1, $restored->getRectifications());
        $this->assertSame('abcd', $restored->getRectifications()[0]->getReference());
        $this->assertSame(0.91, $restored->getRectifications()[0]->getConfidence());
        $this->assertSame(
            ['Expanded the section with supporting detail.'],
            $restored->getRectifications()[0]->getAdjustments()
        );
    }

    public function test_rectification_round_trip_serialization(): void
    {
        $rectification = (new Rectification)
            ->setReference('thin')
            ->setConfidence(0.88)
            ->setAdjustments(['Expanded with examples and metrics.']);

        $restored = Rectification::fromArray($rectification->toArray());

        $this->assertSame('thin', $restored->getReference());
        $this->assertSame(0.88, $restored->getConfidence());
        $this->assertSame(['Expanded with examples and metrics.'], $restored->getAdjustments());
    }

    public function test_illustration_anchor_round_trip_serialization(): void
    {
        $anchor = new IllustrationAnchor('illustration-uuid-1', 'element-uuid-2', false);

        $payload = $anchor->toArray();
        $this->assertSame('illustration-uuid-1', $payload['illustration_identifier']);
        $this->assertSame('element-uuid-2', $payload['element_identifier']);
        $this->assertFalse($payload['is_after']);

        $restored = IllustrationAnchor::fromArray($payload);
        $this->assertSame('illustration-uuid-1', $restored->getIllustrationIdentifier());
        $this->assertSame('element-uuid-2', $restored->getElementIdentifier());
        $this->assertFalse($restored->isAfter());

        $defaultAfter = IllustrationAnchor::fromArray([
            'illustration_identifier' => 'ill-a',
            'element_identifier' => 'el-b',
        ]);
        $this->assertTrue($defaultAfter->isAfter());
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

    public function test_audience_round_trip_serialization_and_normalization(): void
    {
        $audience = (new AudienceContext)
            ->setPriorityWeight(0.65)
            ->setName('SMB Operations Leads')
            ->setDescription('Leads juggling throughput and quality.')
            ->setAgeFrom(28)
            ->setAgeTo(45)
            ->setKnowledgeLevel(KnowledgeLevel::INTERMEDIATE)
            ->setLanguage(Language::EN)
            ->setCountries([Country::US, Country::CA])
            ->setPainPoints(['Manual reporting'])
            ->setConcerns(['Migration risk'])
            ->setAspirations(['Scale with lean team'])
            ->setFears(['Losing stakeholders trust']);

        $payload = $audience->toArray();
        $this->assertSame('Audience priority weight between 0 and 1.', $payload['priority_weight']['description']);
        $this->assertSame(0.65, $payload['priority_weight']['value']);
        $this->assertSame('intermediate', $payload['knowledge_level']['value']);
        $this->assertSame('en', $payload['language']['value']);
        $this->assertSame(['US', 'CA'], $payload['countries']['value']);

        $restored = (new AudienceContext())->loadFromArray($payload);
        $this->assertSame($payload, $restored->toArray());

        $legacy = (new AudienceContext)
            ->setCountries([Country::US, Country::GB])
            ->setKnowledgeLevel(KnowledgeLevel::ADVANCED)
            ->setLanguage(Language::FR);
        $legacyPayload = $legacy->toArray();
        $this->assertSame(['US', 'GB'], $legacyPayload['countries']['value']);
        $this->assertSame('advanced', $legacyPayload['knowledge_level']['value']);
        $this->assertSame('fr', $legacyPayload['language']['value']);
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
