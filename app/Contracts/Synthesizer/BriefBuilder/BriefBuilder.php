<?php

namespace App\Contracts\Synthesizer\BriefBuilder;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;

interface BriefBuilder
{
    public function conceive(Idea $idea, SemanticContext $context): Brief;
}