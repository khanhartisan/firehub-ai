<?php

namespace App\Services\Synthesizer\BriefBuilder\Drivers;

use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Services\Synthesizer\BriefBuilder\BriefBuilderService;

class OpenAIBriefBuilderDriver extends BriefBuilderService
{
    public function conceive(Idea $idea, string $context): Brief
    {
        return (new Brief)
            ->setTemporal($idea->getIntent()->getTemporal())
            ->setTitle($idea->getIntent()->getTitle())
            ->setDescription($idea->getIntent()->getDescription() ?: $context)
            ->setInstructions([
                'Draft with a strong narrative arc and concrete examples.',
                'Use clear, concise language tailored to the target audience.',
            ]);
    }
}
