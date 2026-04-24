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
     * @return RelevantPoint[]
     */
    public function extractIdeaPoints(Idea $idea, string $content): array;

    /**
     * Merge points or spot ones with conflicts
     *
     * @param Idea $idea
     * @param RelevantPoint[] $points
     * @return ConsolidationResult
     */
    public function consolidateIdeaPoints(Idea $idea, array $points): ConsolidationResult;
}