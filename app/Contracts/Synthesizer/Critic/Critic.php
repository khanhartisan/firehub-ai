<?php

namespace App\Contracts\Synthesizer\Critic;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
interface Critic
{
    /**
     * The editorial problem this critic instance reviews (e.g. voice, structure, clarity).
     */
    public function getPurpose(): string;

    /**
     * Given an article, related contexts, and lastest rectifications,
     * return a list of criticisms
     *
     * @param Article $article
     * @param SemanticContext|null $authorContext
     * @param SemanticContext|null $generalContext
     * @param Rectification[] $lastRectifications
     * @return Criticism[]
     */
    public function criticizeArticle(Article $article,
                                     ?SemanticContext $authorContext = null,
                                     ?SemanticContext $generalContext = null,
                                     array $lastRectifications = []): array;
}