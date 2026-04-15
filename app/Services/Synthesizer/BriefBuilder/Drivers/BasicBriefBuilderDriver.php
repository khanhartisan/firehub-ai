<?php

namespace App\Services\Synthesizer\BriefBuilder\Drivers;

use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Services\Synthesizer\BriefBuilder\BriefBuilderService;

class BasicBriefBuilderDriver extends BriefBuilderService
{
    public function conceive(Idea $idea, string $context): Brief
    {
        $intent = $idea->getIntent();
        $instructions = array_filter([
            'Keep claims grounded in source context.',
            'Keep structure concise and scannable.',
            $idea->getReason(),
        ]);

        return (new Brief)
            ->setTemporal($intent->getTemporal())
            ->setTitle($intent->getTitle())
            ->setDescription($intent->getDescription() ?: $context)
            ->setInstructions(array_values($instructions));
    }
}
