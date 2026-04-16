<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Facades\Synthesizer;
use App\Models\Article;

trait HandleOutlineStage
{
    protected function handleOutlineStage(): bool
    {
        $article = $this->article;
        if (! $article instanceof Article or ! $brief = $this->getBrief()) {
            return false;
        }

        $outline = Synthesizer::driver()
            ->getOutlineBuilder()
            ->outline($brief, null);

        $stageData = is_array($article->stage_data) ? $article->stage_data : [];
        $stageData['outline'] = $outline->toArray();
        $article->stage_data = $stageData;
        $article->save();

        return true;
    }

    protected function getOutline(): ?Outline
    {
        $article = $this->article;
        if (! $article instanceof Article || ! is_array($article->stage_data)) {
            return null;
        }

        $rawOutline = data_get($article->stage_data, 'outline');
        if (! is_array($rawOutline)) {
            return null;
        }

        return Outline::fromArray($rawOutline);
    }
}
