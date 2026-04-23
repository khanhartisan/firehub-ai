<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\Synthesizer\IdeaForge\Idea;

interface Researcher
{
    /**
     * Extract points that are related to the ideas from the given content
     *
     * @param Idea $idea
     * @param string $content
     * @return IdeaPoints
     */
    public function extractIdeaPoints(Idea $idea, string $content): IdeaPoints;
}