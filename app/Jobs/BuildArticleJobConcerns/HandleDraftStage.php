<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\Writer\Draft;
use App\Models\Article;

/**
 * DRAFT stage: distills author context (one external call), then produces draft content (one external call).
 * Each sub-step checkpoints (returns null) so the queue slices work across job ticks.
 */
trait HandleDraftStage
{
    /**
     * @return ?true when draft + article copy fields are saved; null while a sub-step is in-flight; null if brief or outline is missing.
     */
    protected function handleDraftStage(): ?bool
    {
        $article = $this->article;
        if (! $article instanceof Article
            or ! $brief = $this->getBrief()
            or ! $outline = $this->getOutline()
        ) {
            return null;
        }

        $distillationProgress = $this->processAuthorContextDistillationForDraft($outline);
        if ($distillationProgress !== true) {
            return $distillationProgress;
        }

        $draftProgress = $this->processDraftGeneration($brief, $outline);
        if ($draftProgress !== true) {
            return $draftProgress;
        }

        $draft = $this->getStageData()->getDraft();
        if (! $draft instanceof Draft) {
            return false;
        }

        $article->title = $draft->getTitle();
        $article->excerpt = $draft->getExcerpt();
        $article->article = $draft->getArticle();
        $this->touchArticleQuietly();

        return true;
    }

    /**
     * Distills the selected author context for the outline (one external call; checkpoint after).
     * Skips when no author is selected or when distillation was already persisted.
     */
    protected function processAuthorContextDistillationForDraft(Outline $outline): ?bool
    {
        $ideaData = $this->getStageData()->getIdeaStageData();
        if (! $ideaData->hasSelectedAuthorContext()) {
            return true;
        }

        if ($this->getStageData()->hasDistilledAuthorContextForDraft()) {
            return true;
        }

        $selectedAuthorContext = $ideaData->getSelectedAuthorContext();
        if (! $selectedAuthorContext instanceof SemanticContext) {
            return true;
        }

        $distilled = $this->synthesizer()
            ->getEditor()
            ->distillAuthorContextForOutline(
                $outline,
                $selectedAuthorContext,
                $this->buildSemanticContext(),
            );

        $this->getStageData()->setDistilledAuthorContextForDraft(
            $distilled instanceof AuthorContext
                ? $distilled
                : AuthorContext::fromArray($distilled->toArray())
        );
        $this->touchArticleQuietly();

        return null;
    }

    /**
     * Calls the writer to produce a draft (one external call; checkpoint after).
     * Skips when a draft payload is already stored on stage data.
     */
    protected function processDraftGeneration(Brief $brief, Outline $outline): ?bool
    {
        if ($this->getStageData()->getDraft() instanceof Draft) {
            return true;
        }

        $authorContext = $this->getStageData()->getDistilledAuthorContextForDraft();

        $draft = $this->synthesizer()
            ->getWriter()
            ->draft($brief, $outline, $authorContext, $this->buildSemanticContext());

        $this->getStageData()->setDraft($draft);
        $this->touchArticleQuietly();

        return null;
    }
}
