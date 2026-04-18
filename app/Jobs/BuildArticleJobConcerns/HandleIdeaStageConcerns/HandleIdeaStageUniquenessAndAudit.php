<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

use App\Contracts\Synthesizer\IdeaForge\Idea;

/**
 * Filters merged ideas for uniqueness, then builds audit reports. Batched (max 20 per run per loop)
 * so long lists do not block a single job forever.
 */
trait HandleIdeaStageUniquenessAndAudit
{
    /**
     * @return ?true when every current idea has been uniqueness-checked; null if more batches remain.
     */
    protected function processUniquenessChecks(): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        $ideaForge = $this->getIdeaForgeService();
        $ideas = $ideaData->getIdeas();
        if ($ideas === []) {
            return false;
        }

        // Resume from last index; batch so one job does not scan thousands of ideas.
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

        // Index reached end of list: all remaining ideas kept; otherwise come back for more batches.
        return $index >= count($ideas) ? true : null;
    }

    /**
     * @return ?true when every surviving idea has an audit row; null if more batches remain.
     */
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

        // Append-only audit list aligned by order with ideas (same batching as uniqueness).
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
