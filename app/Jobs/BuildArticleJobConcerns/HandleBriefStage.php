<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Models\Article;

/**
 * BRIEF stage: builds a {@see Brief} from the picked idea (IDEA stage output) and stores it on {@see StageData}.
 */
trait HandleBriefStage
{
    /**
     * @return ?true once the brief is stored on stage_data; false when article or picked idea is missing (hard failure).
     */
    protected function handleBriefStage(): ?bool
    {
        $article = $this->article;
        if (! $article instanceof Article or ! $idea = $this->getPickedIdea()) {
            return false;
        }

        $context = $this->buildSemanticContext();
        if ($context === null) {
            return false;
        }

        // Add researched points to the context
        $context->set(
            'researched_points',
            'A list of researched points that related to the given idea',
            $this->getStageData()->getResearchStageData()->getPoints()
        );

        $brief = $this->synthesizer()
            ->getBriefBuilder()
            ->conceive($idea, $context);

        $this->getStageData()->setBrief($brief);
        $this->touchArticleQuietly();

        return true;
    }

    /**
     * Resolves the chosen {@see Idea} from the IDEA stage {@see \App\Contracts\Model\Article\StageData} DTO.
     */
    protected function getPickedIdea(): ?Idea
    {
        if (! $this->article instanceof Article) {
            return null;
        }

        return $this->getStageData()->getPickedIdea();
    }

    protected function getBrief(): ?Brief
    {
        $article = $this->article;
        if (! $article instanceof Article) {
            return null;
        }

        return $this->getStageData()->getBrief();
    }
}
