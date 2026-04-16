<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Model\Article\StageData;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Facades\Synthesizer;
use App\Models\Article;

trait HandleOutlineStage
{
    protected function handleOutlineStage(): ?bool
    {
        $article = $this->article;
        if (! $article instanceof Article or ! $brief = $this->getBrief()) {
            return null;
        }

        $outline = Synthesizer::driver()
            ->getOutlineBuilder()
            ->outline($brief, null);

        $stageData = $article->stage_data instanceof StageData
            ? $article->stage_data
            : StageData::fromArray([]);
        $stageData->setOutline($outline->toArray());
        $article->stage_data = $stageData;
        $article->save();

        return true;
    }

    protected function getOutline(): ?Outline
    {
        $article = $this->article;
        if (! $article instanceof Article || ! $article->stage_data instanceof StageData) {
            return null;
        }

        return $article->stage_data->getOutline();
    }
}
