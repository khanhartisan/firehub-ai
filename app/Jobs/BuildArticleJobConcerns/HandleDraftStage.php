<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Model\Article\StageData;
use App\Facades\Synthesizer;
use App\Models\Article;

trait HandleDraftStage
{
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
        $stageData->setDraft($draft->toArray());

        $article->stage_data = $stageData;
        $article->title = $draft->getTitle();
        $article->excerpt = $draft->getExcerpt();
        $article->body_markdown = $draft->getBodyMarkdown();
        $article->save();

        return true;
    }
}
