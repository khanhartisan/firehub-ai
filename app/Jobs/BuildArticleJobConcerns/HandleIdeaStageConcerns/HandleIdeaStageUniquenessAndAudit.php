<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

use App\Contracts\Synthesizer\IdeaForge\Idea;

trait HandleIdeaStageUniquenessAndAudit
{
    protected function processUniquenessChecks(): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        $ideaForge = $this->getIdeaForgeService();
        $ideas = $ideaData->getIdeas();
        if ($ideas === []) {
            return false;
        }

        $index = $ideaData->getUniquenessIndex();
        $remainingChecks = 20;

        while ($remainingChecks > 0 && $index < count($ideas)) {
            $idea = $ideas[$index];
            if (! $idea instanceof Idea) {
                return false;
            }

            // Remove idea in place if it fails uniqueness; keep index on same slot.
            $uniqueness = $ideaForge->getIdeaAuditor()->isIdeaUnique($this->client->id, $idea);
            if ($uniqueness->getIsUnique() === false) {
                array_splice($ideas, $index, 1);
            } else {
                $index++;
            }
            $remainingChecks--;
        }

        $ideaData->setIdeas($ideas);
        $ideaData->setUniquenessIndex($index);
        $this->touchArticleQuietly();

        return $index >= count($ideas) ? true : null;
    }

    protected function processAudits(): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        $ideaForge = $this->getIdeaForgeService();
        $ideas = $ideaData->getIdeas();
        if ($ideas === []) {
            return false;
        }

        $index = $ideaData->getAuditIndex();
        $auditReports = $ideaData->getAuditReports();
        $remainingAudits = 20;

        while ($remainingAudits > 0 && $index < count($ideas)) {
            $idea = $ideas[$index];
            if (! $idea instanceof Idea) {
                return false;
            }

            // Audit is done after uniqueness filtering to avoid unnecessary scoring.
            $auditReports[] = $ideaForge->getIdeaAuditor()->audit($idea);
            $index++;
            $remainingAudits--;
        }

        $ideaData->setAuditReports($auditReports);
        $ideaData->setAuditIndex($index);
        $this->touchArticleQuietly();

        return $index >= count($ideas) ? true : null;
    }
}
