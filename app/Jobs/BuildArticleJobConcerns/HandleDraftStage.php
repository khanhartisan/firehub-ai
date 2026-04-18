<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Models\Article;

/**
 * DRAFT stage: author produces draft content; article title/excerpt/body are set from the draft payload.
 */
trait HandleDraftStage
{
    /**
     * @return ?true when draft + article copy fields are saved; null if brief or outline is missing.
     */
    protected function handleDraftStage(): ?bool
    {
        $article = $this->article;
        if (! $article instanceof Article
            or ! $brief = $this->getBrief()
            or ! $outline = $this->getOutline()
        ) {
            return null;
        }

        $draft = $this->synthesizer()
            ->getAuthor()
            ->draft($brief, $outline);

        $this->getStageData()->setDraft($draft);

        $article->title = $draft->getTitle();
        $article->excerpt = $draft->getExcerpt();
        $article->body_markdown = $draft->getBodyMarkdown();
        $this->touchArticleQuietly();

        return true;
    }
}
