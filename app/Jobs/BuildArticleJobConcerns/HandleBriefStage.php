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
     * @return ?true once the brief is stored on stage_data; null when article or picked idea is missing.
     */
    protected function handleBriefStage(): ?bool
    {
        $article = $this->article;
        if (! $article instanceof Article or ! $idea = $this->getPickedIdea()) {
            return null;
        }

        $brief = $this->synthesizer()
            ->getBriefBuilder()
            ->conceive($idea, (string) $article->context);

        $stageData = $this->getStageData();
        $article->stage_data = $stageData;
        $stageData->setBrief($brief->toArray());
        $this->touchArticleQuietly();

        return true;
    }

    /**
     * Resolves the chosen {@see Idea} from the IDEA stage DTO, with fallbacks for array-shaped persistence.
     */
    protected function getPickedIdea(): ?Idea
    {
        $article = $this->article;
        if (! $article instanceof Article) {
            return null;
        }

        $stageData = $this->getStageData();

        if ($idea = $stageData->getPickedReportIdea()) {
            return $idea;
        }

        // Cast/hydration edge cases: rebuild from array if DTO helpers did not populate objects.
        $rawIdea = data_get($stageData->toArray(), 'idea.picked_report.idea')
            ?? data_get($stageData->toArray(), 'idea.picked_reports.0.idea');
        if (! is_array($rawIdea)) {
            return null;
        }

        return Idea::fromArray($rawIdea);
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
