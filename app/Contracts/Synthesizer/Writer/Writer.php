<?php

namespace App\Contracts\Synthesizer\Writer;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;

interface Writer
{
    public function draft(Brief $brief,
                          Outline $outline,
                          ?SemanticContext $context = null): Draft;

    /**
     * @param Article $article
     * @param IllustrationResult[] $illustrationResults
     * @return IllustrationAnchor[]
     */
    public function getIllustrationAnchors(Article $article, array $illustrationResults): array;
}
