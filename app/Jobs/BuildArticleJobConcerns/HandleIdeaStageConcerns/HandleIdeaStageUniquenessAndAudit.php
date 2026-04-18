<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

/**
 * Filters merged ideas for uniqueness, then builds audit reports for survivors.
 *
 * Uniqueness progress is stored as a list in {@see \App\Contracts\Model\Article\StageData\IdeaStageData::getIdeaUniquenessReports()};
 * each {@see \App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport} carries the idea id via {@see \App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport::getIdeaIdentifier()}. Pending ideas each get {@see \App\Contracts\Synthesizer\IdeaForge\IdeaAuditor::isIdeaUnique()} (the basic driver loads comparison articles once per client per auditor instance), then persists (audit uses its own pass).
 */
trait HandleIdeaStageUniquenessAndAudit
{
    /**
     * @return ?true when every current idea has been uniqueness-checked; false when there are no ideas to check.
     * @throws \Exception
     */
    protected function processUniquenessChecks(): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        $ideaForge = $this->getIdeaForgeService();
        if ($ideaData->getIdeas() === []) {
            return false;
        }

        $auditor = $ideaForge->getIdeaAuditor();
        $performedUniquenessCheck = false;

        foreach ($ideaData->getIdeas() as $idea) {

            if ($ideaData->getIdeaUniquenessReport($idea->getIdentifier())) {
                continue;
            }

            // Perform uniqueness check
            $performedUniquenessCheck = true;
            $uniquenessReport = $auditor->isIdeaUnique($this->client->id, $idea);
            if (!$uniquenessReport->getIdeaIdentifier()) {
                $uniquenessReport->setIdeaIdentifier($idea->getIdentifier());
            }

            // Remove the idea if it's not unique
            if (!$uniquenessReport->getIsUnique()) {
                $ideaData->removeIdeaByIdentifier($idea->getIdentifier());
                break;
            }

            // otherwise add the uniqueness report
            $ideaData->addIdeaUniquenessReport($uniquenessReport);
        }

        $this->touchArticleQuietly();

        return $performedUniquenessCheck ? null : true;
    }

    /**
     * @return ?true when every surviving idea has an audit row; false when there are no ideas.
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

        while ($index < count($ideas)) {
            $idea = $ideas[$index];

            // Audit is done after uniqueness filtering to avoid unnecessary scoring.
            $auditReports[] = $ideaForge->getIdeaAuditor()->audit($idea);
            $index++;
        }

        $ideaData->setAuditReports($auditReports);
        $ideaData->setAuditIndex($index);
        $this->touchArticleQuietly();

        return true;
    }
}
