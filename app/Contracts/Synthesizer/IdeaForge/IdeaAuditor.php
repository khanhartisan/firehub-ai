<?php

namespace App\Contracts\Synthesizer\IdeaForge;

interface IdeaAuditor
{
    /**
     * Validate if the idea is unique
     *
     * @param string $clientId
     * @param Idea $idea
     * @return IdeaUniquenessReport
     */
    public function isIdeaUnique(string $clientId, Idea $idea): IdeaUniquenessReport;

    /**
     * Given an idea and give an audit report
     *
     * @param Idea $idea
     * @return IdeaAuditReport
     */
    public function audit(Idea $idea): IdeaAuditReport;
}