<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Facades\Synthesizer;
use App\Models\Article;

trait HandleDraftStage
{
    protected function handleDraftStage(): bool
    {
        $article = $this->article;
        if (! $article instanceof Article
            or ! $brief = $this->getBrief()
            or ! $outline = $this->getOutline()
        ) {
            return false;
        }

        $draft = Synthesizer::driver()
            ->getAuthor()
            ->draft($brief, $outline);

        $stageData = is_array($article->stage_data) ? $article->stage_data : [];
        $stageData['draft'] = $draft->toArray();

        $article->stage_data = $stageData;
        $article->title = $draft->getTitle();
        $article->excerpt = $draft->getExcerpt();
        $article->body_markdown = $draft->getBodyMarkdown();
        $article->save();

        return true;
    }
}
