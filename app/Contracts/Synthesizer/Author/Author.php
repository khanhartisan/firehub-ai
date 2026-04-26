<?php

namespace App\Contracts\Synthesizer\Author;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;

interface Author
{
    public function draft(Brief $brief,
                          Outline $outline,
                          ?SemanticContext $context = null): Draft;
}