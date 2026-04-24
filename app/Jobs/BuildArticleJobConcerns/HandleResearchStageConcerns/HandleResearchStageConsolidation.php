<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns;

use App\Contracts\Synthesizer\IdeaForge\Idea;

trait HandleResearchStageConsolidation
{
    protected function consolidateAccumulatedPoints(Idea $pickedIdea): bool
    {
        $researchData = $this->getStageData()->getResearchStageData();
        $pointsByPageUrl = $researchData->getPointsByPageUrl();
        if ($pointsByPageUrl === []) {
            return false;
        }

        foreach ($pointsByPageUrl as $url => $pagePoints) {
            $researcher = $this->synthesizer()->getResearcher();
            $input = array_merge($researchData->getPoints(), $pagePoints);
            $result = $researcher->consolidateIdeaPoints($pickedIdea, $input);

            $researchData->setPoints($result->getPoints());
            $researchData->setConflicts(array_merge($researchData->getConflicts(), $result->getConflicts()));
            $researchData->removePagePoints($url);

            $this->touchArticleQuietly();

            return true;
        }

        return false;
    }
}
