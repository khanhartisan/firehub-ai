<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers;

use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\IdeaAuditorService;

/**
 * Test / local stub: no vector DB, embeddings, or external APIs for uniqueness.
 */
class BasicIdeaAuditorDriver extends IdeaAuditorService
{
    public function isIdeaUnique(string $clientId, Idea $idea): IdeaUniquenessReport
    {
        return (new IdeaUniquenessReport)
            ->setClientId($clientId)
            ->setIdeaIdentifier(trim((string) $idea->getIdentifier()))
            ->setSimilarity(0.2)
            ->setIsUnique(true)
            ->setSimilarArticles([]);
    }

    public function audit(Idea $idea): IdeaAuditReport
    {
        $confidence = $idea->getConfidence() ?? 0.5;
        $score = max(0.0, min(1.0, $confidence));

        $highlights = [
            sprintf('Temporal angle: %s.', $idea->getIntent()->getTemporal()?->value ?? 'n/a'),
            sprintf('Intent focus: %s.', implode(', ', array_map(static fn ($type) => $type->name, $idea->getIntent()->getTypes()))),
        ];

        $concerns = [];
        if ($score < 0.6) {
            $concerns[] = 'Low confidence idea. Consider adding more context constraints.';
        }

        if (trim((string) $idea->getIntent()->getDescription()) === '') {
            $concerns[] = 'Idea description is empty.';
        }

        return new IdeaAuditReport($idea, $score, $highlights, $concerns);
    }
}
