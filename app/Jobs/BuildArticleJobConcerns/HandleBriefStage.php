<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Model\Article\StageData;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Facades\Synthesizer;
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

        $brief = Synthesizer::driver()
            ->getBriefBuilder()
            ->conceive($idea, (string) $article->context);

        $stageData = $article->stage_data instanceof StageData
            ? $article->stage_data
            : StageData::fromArray([]);
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
        if (! $article instanceof Article || ! $article->stage_data instanceof StageData) {
            return null;
        }

        if ($idea = $article->stage_data->getPickedReportIdea()) {
            return $idea;
        }

        // Cast/hydration edge cases: rebuild from array if DTO helpers did not populate objects.
        $rawIdea = data_get($article->stage_data->toArray(), 'idea.picked_report.idea')
            ?? data_get($article->stage_data->toArray(), 'idea.picked_reports.0.idea');
        if (! is_array($rawIdea)) {
            return null;
        }

        return Idea::fromArray($rawIdea);
    }

    protected function getBrief(): ?Brief
    {
        $article = $this->article;
        if (! $article instanceof Article || ! $article->stage_data instanceof StageData) {
            return null;
        }

        return $article->stage_data->getBrief();
    }
}
