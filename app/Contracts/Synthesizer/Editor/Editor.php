<?php

namespace App\Contracts\Synthesizer\Editor;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;

interface Editor
{
    /**
     * Pick the best author context for the given idea.
     *
     * @param Idea $idea
     * @param SemanticContext[] $authorContexts
     * @return SemanticContext
     */
    public function determineAuthorContext(Idea $idea, array $authorContexts): SemanticContext;

    /**
     * Refine the given author context, reset weights, remove unnecessary fields...
     *
     * @param Outline $outline The outline we are working on
     * @param string $outlineItemIdentifier The identifier of the outline item that we are focusing on
     * @param SemanticContext $authorContext The full author context
     * @param ?SemanticContext $generalContext General context to help the agent understand better
     * @return SemanticContext Distilled author context
     */
    public function distillOutlineAuthorContext(Outline $outline,
                                         string $outlineItemIdentifier,
                                         SemanticContext $authorContext,
                                         ?SemanticContext $generalContext = null): SemanticContext;
}
