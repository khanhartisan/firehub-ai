<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

use App\Contracts\Model\Article\StageData;
use App\Contracts\Model\Article\StageData\IdeaStageData;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\IdeaForge;
use App\Facades\Synthesizer;

/**
 * Shared IDEA-stage accessors: {@see StageData} on the article, cached IdeaForge and advisor list.
 */
trait HandleIdeaStageContext
{
    /** @var IdeaAdvisor[]|null Cached per job instance to avoid repeated container resolution. */
    protected ?array $resolvedIdeaAdvisors = null;

    protected ?IdeaForge $resolvedIdeaForge = null;

    protected function getIdeaStageData(): IdeaStageData
    {
        return $this->getStageData()->getIdeaStageData();
    }

    /** @return IdeaAdvisor[] */
    protected function getIdeaAdvisors(): array
    {
        if (is_array($this->resolvedIdeaAdvisors)) {
            return $this->resolvedIdeaAdvisors;
        }

        $this->resolvedIdeaAdvisors = array_values($this->getIdeaForgeService()->getIdeaAdvisors());

        return $this->resolvedIdeaAdvisors;
    }

    protected function getIdeaForgeService(): IdeaForge
    {
        return $this->resolvedIdeaForge ??= Synthesizer::getIdeaForge();
    }

    /**
     * Ensures {@see Article::$stage_data} is a {@see StageData} DTO (hydrates from DB cast when present).
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
