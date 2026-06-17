<?php

namespace App\Services\Synthesizer\Tagger\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Services\Synthesizer\Tagger\TaggerService;

class BasicTaggerDriver extends TaggerService
{
    /**
     * @return string[]
     */
    public function suggestTags(
        Article $article,
        ?SemanticContext $authorContext = null,
        ?SemanticContext $generalContext = null
    ): array {
        $text = trim(strip_tags($article->toHtml()));
        if ($text === '') {
            return ['untagged'];
        }

        return ['general'];
    }
}
