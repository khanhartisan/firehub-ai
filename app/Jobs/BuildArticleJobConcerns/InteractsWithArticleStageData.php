<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Model\Article\StageData;

/**
 * Ensures {@see \App\Models\Article::$stage_data} is a {@see StageData} DTO for all pipeline stages.
 */
trait InteractsWithArticleStageData
{
    /**
     * Hydrates from the model cast when present; otherwise assigns an empty root DTO.
     */
    protected function getStageData(): StageData
    {
        if ($this->article->stage_data instanceof StageData) {
            return $this->article->stage_data;
        }

        $this->article->stage_data = StageData::fromArray([]);

        return $this->article->stage_data;
    }
}
