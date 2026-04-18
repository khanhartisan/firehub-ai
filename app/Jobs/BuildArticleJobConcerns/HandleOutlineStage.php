<?php

namespace App\Jobs\BuildArticleJobConcerns;

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

        $outline = $this->synthesizer()
            ->getOutlineBuilder()
            ->outline($brief, null);

        $stageData = $this->getStageData();
        $article->stage_data = $stageData;
        $stageData->setOutline($outline->toArray());
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
