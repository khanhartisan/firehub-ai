<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Models\Article;

/**
 * OUTLINE stage: derives an {@see Outline} from the brief.
 */
trait HandleOutlineStage
{
    /**
     * @return ?true when outline is stored; null when brief is not available yet.
     */
    protected function handleOutlineStage(): ?bool
    {
        $article = $this->article;
        if (! $article instanceof Article or ! $brief = $this->getBrief()) {
            return null;
        }

        $context = $this->buildSemanticContext() ?? new SemanticContext;
        $context->set(
            'researched_points',
            'A list of researched points that related to the given idea',
            $this->getStageData()->getResearchStageData()->getPoints()
        );

        $outline = $this->synthesizer()
            ->getOutlineBuilder()
            ->outline($brief, $context);

        $selectedAuthorContext = $this->getStageData()->getIdeaStageData()->getSelectedAuthorContext();
        if ($selectedAuthorContext instanceof SemanticContext) {
            $outline = $this->synthesizer()
                ->getEditor()
                ->tailorOutlineForAuthor($outline, $selectedAuthorContext);
        }

        $this->getStageData()->setOutline($outline);
        $this->touchArticleQuietly();

        return true;
    }

    protected function getOutline(): ?Outline
    {
        $article = $this->article;
        if (! $article instanceof Article) {
            return null;
        }

        return $this->getStageData()->getOutline();
    }
}
