<?php

namespace Tests\Unit\Services\Synthesizer\Editor;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Model\Author\AuthorContexts\LinguisticContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Services\Synthesizer\Editor\Drivers\BasicEditorDriver;
use Tests\TestCase;

class EditorDriversTest extends TestCase
{
    public function test_determine_author_context_picks_best_overlap_match(): void
    {
        $driver = new BasicEditorDriver;
        $idea = new Idea($this->makeIntent(), 0.8, 'Practical SaaS onboarding playbook');

        $weakMatch = (new SemanticContext)->set(
            'voice',
            'Author voice',
            'Formal academic commentary on macroeconomics'
        );
        $strongMatch = (new SemanticContext)->set(
            'voice',
            'Author voice',
            'Practical SaaS onboarding guidance for operators'
        );

        $picked = $driver->determineAuthorContext($idea, [$weakMatch, $strongMatch]);

        $this->assertSame(
            'Practical SaaS onboarding guidance for operators',
            $picked->getVoiceValue()
        );
    }

    public function test_determine_author_context_requires_at_least_one_context(): void
    {
        $driver = new BasicEditorDriver;
        $idea = new Idea($this->makeIntent(), 0.8, 'Test');

        $this->expectException(\InvalidArgumentException::class);
        $driver->determineAuthorContext($idea, []);
    }

    public function test_tailor_outline_for_author_prepends_author_directives_to_guidelines(): void
    {
        $driver = new BasicEditorDriver;
        $item = (new OutlineItem)->setPoint(
            (new RelevantPoint)
                ->setHeadline('Main insights')
                ->setDescription('Core body section.')
        );
        $item->setGuidelines(['Prioritize practical takeaways.']);
        $outline = (new Outline)->setTitle('Draft')->setItems([$item]);

        $authorContext = (new SemanticContext)->set(
            'voice',
            'Author voice',
            'Practical operator tone with concrete metrics'
        );

        $tailored = $driver->tailorOutlineForAuthor($outline, $authorContext);
        $guidelines = $tailored->getItems()[0]->getGuidelines();

        $this->assertStringContainsString('[Author]', $guidelines[0]);
        $this->assertStringContainsString('Practical operator tone', $guidelines[0]);
        $this->assertContains('Prioritize practical takeaways.', $guidelines);
    }

    public function test_distill_author_context_for_outline_includes_outline_summary_and_general_context(): void
    {
        $driver = new BasicEditorDriver;
        $item = (new OutlineItem)->setPoint(
            (new RelevantPoint)
                ->setHeadline('Activation tactics')
                ->setDescription('Explain onboarding experiments.')
        );
        $outline = (new Outline)
            ->setTitle('SaaS onboarding playbook')
            ->setItems([$item]);

        $authorContext = (new AuthorContext)
            ->setLinguisticContext(
                (new LinguisticContext)
                    ->setVocabularyTier('Colloquial', 2.0)
            );

        $generalContext = (new SemanticContext)->set(
            'outline_focus',
            'Additional outline focus.',
            'Emphasize trade-offs'
        );

        $distilled = $driver->distillAuthorContextForOutline($outline, $authorContext, $generalContext);

        $this->assertInstanceOf(AuthorContext::class, $distilled);
        $this->assertNull($distilled->getLinguisticContextWeight());
        $this->assertSame('SaaS onboarding playbook', $distilled->getOutlineTitleValue());
        $this->assertSame(
            [['headline' => 'Activation tactics', 'description' => 'Explain onboarding experiments.']],
            $distilled->getOutlineSectionsValue()
        );
        $this->assertSame('Emphasize trade-offs', $distilled->getGeneralOutlineFocusValue());
    }

    public function test_distill_author_context_for_outline_item_focuses_section_and_resets_weights(): void
    {
        $driver = new BasicEditorDriver;
        $item = (new OutlineItem)->setPoint(
            (new RelevantPoint)
                ->setHeadline('Activation tactics')
                ->setDescription('Explain onboarding experiments.')
        );
        $item->setGuidelines(['Use concrete metrics.']);
        $itemId = $item->getIdentifier();

        $outline = (new Outline)->setItems([$item]);

        $authorContext = (new AuthorContext)
            ->setLinguisticContext(
                (new LinguisticContext)
                    ->setVocabularyTier('Colloquial', 2.0)
            )
            ->set('empty_field', 'Unused field', '', 5.0);

        $generalContext = (new SemanticContext)->set(
            'outline_focus',
            'Additional outline focus.',
            'Emphasize trade-offs'
        );

        $distilled = $driver->distillAuthorContextForOutlineItem(
            $outline,
            $itemId,
            $authorContext,
            $generalContext
        );

        $this->assertInstanceOf(AuthorContext::class, $distilled);
        $this->assertNull($distilled->getLinguisticContextWeight());
        $this->assertSame('Activation tactics', $distilled->getSectionHeadlineValue());
        $this->assertSame('Explain onboarding experiments.', $distilled->getSectionDescriptionValue());
        $this->assertSame(['Use concrete metrics.'], $distilled->getSectionGuidelinesValue());
        $this->assertSame('Emphasize trade-offs', $distilled->getGeneralOutlineFocusValue());
        $this->assertFalse($distilled->has('empty_field'));
    }

    public function test_distill_author_context_for_outline_item_throws_when_item_missing(): void
    {
        $driver = new BasicEditorDriver;
        $outline = (new Outline)->setItems([]);
        $authorContext = (new SemanticContext)->set('tone', 'Tone', 'Practical');

        $this->expectException(\InvalidArgumentException::class);
        $driver->distillAuthorContextForOutlineItem($outline, 'missing-id', $authorContext);
    }

    protected function makeIntent(): Intent
    {
        return (new Intent)
            ->setTitle('SaaS onboarding playbook')
            ->setDescription('How teams improve activation with practical experiments.')
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::EVERGREEN)
            ->setTypes([IntentType::INFORMATIONAL]);
    }
}
