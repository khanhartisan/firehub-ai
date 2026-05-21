<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\CommonData\Fact;
use App\Contracts\CommonData\SemanticContext;
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

    /**
     * Be given a list of verified facts,
     * base on the facts provided to return a new RelevantPoint
     * for the given idea from the ConflictedPoints.
     * May return null if unresolvable
     *
     * @param Idea $idea
     * @param ConflictedPoints $conflictedPoints
     * @param Fact[] $facts
     * @return ?RelevantPoint
     */
    public function resolveIdeaConflictedPointsByFacts(Idea $idea,
                                                ConflictedPoints $conflictedPoints,
                                                array $facts): ?RelevantPoint;

    /**
     * Based on the bias from the author perspective, resolve the conflicted points.
     * May return null if unresolvable
     *
     * @param SemanticContext $authorContext
     * @param Idea $idea
     * @param ConflictedPoints $conflictedPoints
     * @param array $facts
     * @return ?RelevantPoint
     */
    public function resolveIdeaConflictedPointsByAuthorContext(SemanticContext $authorContext,
                                                               Idea $idea,
                                                               ConflictedPoints $conflictedPoints,
                                                               array $facts = []): ?RelevantPoint;
}