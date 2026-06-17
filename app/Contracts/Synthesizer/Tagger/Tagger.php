<?php

namespace App\Contracts\Synthesizer\Tagger;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;

interface Tagger
{
    /**
     * @return string[]
     */
    public function suggestTags(
        Article $article,
        ?SemanticContext $authorContext = null,
        ?SemanticContext $generalContext = null
    ): array;
}
