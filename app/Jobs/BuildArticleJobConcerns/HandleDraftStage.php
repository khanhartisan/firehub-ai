<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Model\Article\StageData;
use App\Facades\Synthesizer;
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

        $draft = Synthesizer::driver()
            ->getAuthor()
            ->draft($brief, $outline);

        $stageData = $article->stage_data instanceof StageData
            ? $article->stage_data
            : StageData::fromArray([]);
        $article->stage_data = $stageData;
        $stageData->setDraft($draft->toArray());

        $article->title = $draft->getTitle();
        $article->excerpt = $draft->getExcerpt();
        $article->body_markdown = $draft->getBodyMarkdown();
        $this->touchArticleQuietly();

        return true;
    }
}
