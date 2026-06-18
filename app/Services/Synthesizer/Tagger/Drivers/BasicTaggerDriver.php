<?php

namespace App\Services\Synthesizer\Tagger\Drivers;

use App\Contracts\CommonData\SemanticContext;
use App\Services\Synthesizer\Tagger\TaggerService;

class BasicTaggerDriver extends TaggerService
{
    /**
     * @return string[]
     */
    public function suggestTags(
        string $content,
        ?SemanticContext $authorContext = null,
        ?SemanticContext $generalContext = null
    ): array {
        if (trim($content) === '') {
            return ['untagged'];
        }

        return ['general'];
    }
}
