<?php

namespace App\Contracts\Synthesizer\Tagger;

use App\Contracts\CommonData\SemanticContext;

interface Tagger
{
    /**
     * @return string[]
     */
    public function suggestTags(
        string $content,
        ?SemanticContext $authorContext = null,
        ?SemanticContext $generalContext = null
    ): array;
}
