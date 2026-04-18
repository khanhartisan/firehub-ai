<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

use App\Contracts\Model\Article\StageData\IdeaStageData;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\IdeaForge;

/**
 * Shared IDEA-stage accessors: {@see getIdeaStageData()} and cached advisor list.
 * {@see getStageData()} and {@see synthesizer()} come from job-level traits.
 */
trait HandleIdeaStageContext
{
    /** @var IdeaAdvisor[]|null Cached per job instance to avoid repeated container resolution. */
    protected ?array $resolvedIdeaAdvisors = null;

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
        return $this->synthesizer()->getIdeaForge();
    }
}
