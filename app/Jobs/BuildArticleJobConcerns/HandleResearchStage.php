<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageConflictResolution;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageConsolidation;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageKeywordBootstrap;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStageKeywordTracking;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStagePointExtraction;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns\HandleResearchStagePointVerification;
use App\Utils\Debugger;
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
        Debugger::devConsoleDump('Article: '.$this->articleId.'. Handling research stage');

        $pickedIdea = $this->getStageData()->getPickedIdea();
        if ($pickedIdea === null) {
            Debugger::devConsoleDump('No picked idea found');
            return false;
        }

        $bootstrapResult = $this->bootstrapResearchKeywords($pickedIdea);
        if ($bootstrapResult !== null) {
            Debugger::devConsoleDump('BootstrapResearchKeyword is empty.');
            return $bootstrapResult;
        }

        $keywords = $this->getTrackedResearchKeywords();
        if ($keywords->isEmpty()) {
            Debugger::devConsoleDump('Keywords data is empty.');
            return true;
        }

        if ($this->hasPendingKeywords($keywords)) {
            Debugger::devConsoleDump('Has pending keywords.');
            $this->reDispatchDelay = 10;
            return null;
        }

        $didExtractOnePage = $this->extractAndStorePointsByPage($pickedIdea, $keywords);
        if ($didExtractOnePage) {
            Debugger::devConsoleDump('Did extract one page. Re-dispatching...');
            // Enforce one external extraction call per job run.
            return null;
        }

        $didConsolidateOnePage = $this->consolidateAccumulatedPoints($pickedIdea);
        if ($didConsolidateOnePage) {
            Debugger::devConsoleDump('Did consolidate one page. Re-dispatching...');
            // Consolidate incrementally so each run stays bounded.
            return null;
        }

        $didResolveOneConflict = $this->resolveOneConflict($pickedIdea);
        if ($didResolveOneConflict) {
            Debugger::devConsoleDump('Did resolve one conflict. Re-dispatching...');
            // Resolve conflicts incrementally after consolidation completes.
            return null;
        }

        $didConsolidateResolvedConflicts = $this->consolidateResolvedConflictPoints($pickedIdea);
        if ($didConsolidateResolvedConflicts) {
            Debugger::devConsoleDump('Did consolidate resolved conflicts. Re-dispatching...');
            // Re-merge newly resolved points into central points/conflicts.
            return null;
        }

        $didVerifyOnePoint = $this->verifyOnePendingPoint($pickedIdea);
        if ($didVerifyOnePoint) {
            Debugger::devConsoleDump('Did verify one point. Re-dispatching...');
            // Verify central points incrementally.
            return null;
        }

        $didRemoveLowConfidencePoints = $this->removeLowConfidencePoints();
        if ($didRemoveLowConfidencePoints) {
            Debugger::devConsoleDump('Did remove low confidence points. Re-dispatching...');
            // Prune low-confidence points after verification.
            return null;
        }

        Debugger::devConsoleDump('Done researching stage.');

        return true;
    }
}