<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Models\Article;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

/**
 * TAGGING stage: suggests tags for the outline via the synthesizer tagger.
 */
trait HandleTaggingStage
{
    /**
     * @return ?true when suggested tags are stored; null when outline is not available yet.
     */
    protected function handleTaggingStage(): ?bool
    {
        $article = $this->article;
        if (! $article instanceof Article or ! $outline = $this->getOutline()) {
            return null;
        }

        $taggingData = $this->getStageData()->getTaggingStageData();
        if (! $taggingData->hasSuggestedTags()) {
            $authorContext = $this->getStageData()->getIdeaStageData()->getSelectedAuthorContext();
            $tags = $this->synthesizer()
                ->getTagger()
                ->suggestTags(
                    $this->outlineToTaggingContent($outline),
                    $authorContext instanceof AuthorContext ? $authorContext : null,
                    $this->buildSemanticContext(),
                );

            $taggingData->setSuggestedTags($tags);
        }

        $this->syncArticleTags($article, $taggingData->getSuggestedTags());
        $this->touchArticleQuietly();

        return true;
    }

    /**
     * @param  string[]  $tagNames
     */
    protected function syncArticleTags(Article $article, array $tagNames): void
    {
        DB::transaction(function () use ($article, $tagNames): void {
            $tagIds = collect($tagNames)
                ->map(fn (string $name): string => Tag::query()->firstOrCreate(['name' => $name])->id)
                ->all();

            $article->tags()->sync($tagIds);
        });
    }

    protected function outlineToTaggingContent(Outline $outline): string
    {
        $lines = [];

        if ($title = $outline->getTitle()) {
            $lines[] = $title;
        }

        foreach ($outline->getItems() as $item) {
            $itemContent = $this->outlineItemToTaggingContent($item, 0);
            if ($itemContent !== '') {
                $lines[] = $itemContent;
            }
        }

        return trim(implode("\n", $lines));
    }

    protected function outlineItemToTaggingContent(OutlineItem $item, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        $lines = [];
        $point = $item->getPoint();

        if ($headline = $point->getHeadline()) {
            $lines[] = $indent.$headline;
        }

        if ($description = $point->getDescription()) {
            $lines[] = $indent.$description;
        }

        foreach ($item->getGuidelines() as $guideline) {
            $lines[] = $indent.'- '.$guideline;
        }

        foreach ($item->getSubItems() as $subItem) {
            $subItemContent = $this->outlineItemToTaggingContent($subItem, $depth + 1);
            if ($subItemContent !== '') {
                $lines[] = $subItemContent;
            }
        }

        return implode("\n", $lines);
    }
}
