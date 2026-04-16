<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Facades\Synthesizer;
use App\Models\Article;

trait HandleBriefStage
{
    protected function handleBriefStage(): bool
    {
        $article = $this->article;
        if (! $article instanceof Article or ! $idea = $this->getPickedIdea()) {
            return false;
        }

        $brief = Synthesizer::driver()
            ->getBriefBuilder()
            ->conceive($idea, (string) $article->context);

        $stageData = is_array($article->stage_data) ? $article->stage_data : [];
        $stageData['brief'] = $brief->toArray();
        $article->stage_data = $stageData;
        $article->save();

        return true;
    }

    protected function getPickedIdea(): ?Idea
    {
        $article = $this->article;
        if (! $article instanceof Article || ! is_array($article->stage_data)) {
            return null;
        }

        $rawIdea = data_get($article->stage_data, 'idea.picked_report.idea');
        if (! is_array($rawIdea)) {
            return null;
        }

        return Idea::fromArray($rawIdea);
    }

    protected function getBrief(): ?Brief
    {
        $article = $this->article;
        if (! $article instanceof Article || ! is_array($article->stage_data)) {
            return null;
        }

        $rawBrief = data_get($article->stage_data, 'brief');
        if (! is_array($rawBrief)) {
            return null;
        }

        return Brief::fromArray($rawBrief);
    }
}
