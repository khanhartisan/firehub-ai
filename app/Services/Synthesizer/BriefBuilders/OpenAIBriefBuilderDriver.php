<?php

namespace App\Services\Synthesizer\BriefBuilders;

use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Services\Synthesizer\BriefBuilderService;

class OpenAIBriefBuilderDriver extends BriefBuilderService
{
    public function conceive(Idea $idea, string $context): Brief
    {
        // TODO: Implement conceive() method.
    }
}