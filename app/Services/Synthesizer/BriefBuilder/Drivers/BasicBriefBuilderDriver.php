<?php

namespace App\Services\Synthesizer\BriefBuilder\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Services\Synthesizer\BriefBuilder\BriefBuilderService;

class BasicBriefBuilderDriver extends BriefBuilderService
{
    public function conceive(Idea $idea, SemanticContext $context): Brief
    {
        $intent = $idea->getIntent();
        $fallbackDescription = trim((string) ($context->getArticleContextValue() ?? $context->getDescriptionValue() ?? ''));
        $instructions = array_filter([
            'Keep claims grounded in source context.',
            'Keep structure concise and scannable.',
            $idea->getReason(),
        ]);

        return (new Brief)
            ->setTemporal($intent->getTemporal())
            ->setTitle($intent->getTitle())
            ->setDescription($intent->getDescription() ?: $fallbackDescription)
            ->setInstructions(array_values($instructions));
    }
}
