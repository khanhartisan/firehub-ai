<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageKeywordBootstrap;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageKeywordTracking;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStagePointExtraction;
use Illuminate\Support\Collection;

trait HandleResearchStage
{
    use HandleResearchStageKeywordBootstrap;
    use HandleResearchStageKeywordTracking;
    use HandleResearchStagePointExtraction;

    protected function handleResearchStage(): ?bool
    {
        $pickedIdea = $this->getStageData()->getPickedIdea();
        if ($pickedIdea === null) {
            return false;
        }

        $bootstrapResult = $this->bootstrapResearchKeywords($pickedIdea);
        if ($bootstrapResult !== null) {
            return $bootstrapResult;
        }

        $keywords = $this->getTrackedResearchKeywords();
        if ($keywords->isEmpty()) {
            return true;
        }

        if ($this->hasPendingKeywords($keywords)) {
            return null;
        }

        $didExtractOnePage = $this->extractAndStorePointsByPage($pickedIdea, $keywords);
        if ($didExtractOnePage) {
            // Enforce one external extraction call per job run.
            return null;
        }

        return true;
    }
}