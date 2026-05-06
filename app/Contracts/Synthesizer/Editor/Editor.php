<?php

namespace App\Contracts\Synthesizer\Editor;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\BriefBuilder\Brief;

interface Editor
{
    /**
     * Pick the best author context for the given brief
     * May return null if none appropriate
     *
     * @param Brief $brief
     * @param SemanticContext[] $authorContexts
     * @return ?SemanticContext
     */
    public function determineAuthorContexts(Brief $brief, array $authorContexts): ?SemanticContext;

    // TODO: Continue designing this interface
}