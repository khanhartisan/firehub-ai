<?php

namespace App\Contracts\Synthesizer\IdeaForge;

use App\Contracts\CommonData\SemanticContext;

interface IdeaPicker
{
    /**
     * Given a list of idea audit reports, pick the best ones
     * Return null if we can't pick any.
     *
     * @param IdeaAuditReport[] $ideaAuditReports
     * @param SemanticContext $context
     * @param int $limit
     * @return IdeaAuditReport[]|null
     */
    public function pick(array $ideaAuditReports, SemanticContext $context, int $limit = 1): ?array;
}