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

    public function test_distill_outline_author_context_focuses_section_and_resets_weights(): void
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

        $distilled = $driver->distillOutlineAuthorContext(
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

    public function test_distill_outline_author_context_throws_when_item_missing(): void
    {
        $driver = new BasicEditorDriver;
        $outline = (new Outline)->setItems([]);
        $authorContext = (new SemanticContext)->set('tone', 'Tone', 'Practical');

        $this->expectException(\InvalidArgumentException::class);
        $driver->distillOutlineAuthorContext($outline, 'missing-id', $authorContext);
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
