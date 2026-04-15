<?php

namespace App\Contracts\Synthesizer\IdeaForge;

interface IdeaPicker
{
    /**
     * Given a list of idea audit reports, pick the best ones
     * Return null if we can't pick any.
     *
     * @param IdeaAuditReport[] $ideaAuditReports
     * @param string $context
     * @param int $limit
     * @return IdeaAuditReport[]|null
     */
    public function pick(array $ideaAuditReports, string $context, int $limit = 1): ?array;
}