<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageConsolidation;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageKeywordBootstrap;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageKeywordTracking;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStagePointExtraction;
use Illuminate\Support\Collection;

trait HandleResearchStage
{
    use HandleResearchStageKeywordBootstrap;
    use HandleResearchStageKeywordTracking;
    use HandleResearchStagePointExtraction;
    use HandleResearchStageConsolidation;

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

        $didConsolidateOnePage = $this->consolidateAccumulatedPoints($pickedIdea);
        if ($didConsolidateOnePage) {
            // Consolidate incrementally so each run stays bounded.
            return null;
        }

        return true;
    }
}