<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\CommonData\Point;
use App\Contracts\Synthesizer\IdeaForge\Idea;

interface Researcher
{
    /**
     * Extract points that are related to the ideas from the given content
     *
     * @param Idea $idea
     * @param string $content
     * @return Point[]
     */
    public function extractIdeaPoints(Idea $idea, string $content): array;
}