<?php

namespace App\Contracts\Synthesizer\Author;

use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;

interface Author
{
    public function draft(Brief $brief, Outline $outline, ?string $prompt = null): Draft;
}