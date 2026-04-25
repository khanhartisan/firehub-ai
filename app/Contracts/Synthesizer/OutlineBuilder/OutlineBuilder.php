<?php

namespace App\Contracts\Synthesizer\OutlineBuilder;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\BriefBuilder\Brief;

interface OutlineBuilder
{
    public function outline(Brief $brief, ?SemanticContext $context): Outline;
}