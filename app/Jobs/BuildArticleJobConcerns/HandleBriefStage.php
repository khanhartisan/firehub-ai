<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Model\Article\StageData;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Facades\Synthesizer;
use App\Models\Article;

trait HandleBriefStage
{
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

    protected function getPickedIdea(): ?Idea
    {
        $article = $this->article;
        if (! $article instanceof Article || ! $article->stage_data instanceof StageData) {
            return null;
        }

        if ($idea = $article->stage_data->getPickedReportIdea()) {
            return $idea;
        }

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
