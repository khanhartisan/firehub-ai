<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageConflictResolution;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageConsolidation;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageKeywordBootstrap;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageKeywordTracking;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStagePointExtraction;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStagePointVerification;
use Illuminate\Support\Collection;

trait HandleResearchStage
{
    use HandleResearchStageKeywordBootstrap;
    use HandleResearchStageKeywordTracking;
    use HandleResearchStagePointExtraction;
    use HandleResearchStageConsolidation;
    use HandleResearchStageConflictResolution;
    use HandleResearchStagePointVerification;

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
            $this->reDispatchDelay = 10;
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

        $didResolveOneConflict = $this->resolveOneConflict($pickedIdea);
        if ($didResolveOneConflict) {
            // Resolve conflicts incrementally after consolidation completes.
            return null;
        }

        $didConsolidateResolvedConflicts = $this->consolidateResolvedConflictPoints($pickedIdea);
        if ($didConsolidateResolvedConflicts) {
            // Re-merge newly resolved points into central points/conflicts.
            return null;
        }

        $didVerifyOnePoint = $this->verifyOnePendingPoint($pickedIdea);
        if ($didVerifyOnePoint) {
            // Verify central points incrementally.
            return null;
        }

        $didRemoveLowConfidencePoints = $this->removeLowConfidencePoints();
        if ($didRemoveLowConfidencePoints) {
            // Prune low-confidence points after verification.
            return null;
        }

        return true;
    }
}