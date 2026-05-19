<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns;

use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Enums\KeywordStatus;
use App\Models\Keyword;
use App\Models\Page;
use App\Utils\UrlNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HandleResearchStagePointExtraction
{
    /**
     * @param  Collection<int, Keyword>  $keywords
     */
    protected function extractAndStorePointsByPage(Idea $pickedIdea, Collection $keywords): bool
    {
        $researchData = $this->getStageData()->getResearchStageData();
        if ($researchData->isPagePointExtractionCompleted()) {
            return false;
        }

        $researchedKeywords = $keywords
            ->where('status', KeywordStatus::RESEARCHED)
            ->pluck('id')
            ->all();

        if ($researchedKeywords === []) {
            $researchData->setPagePointExtractionCompleted(true);
            $this->touchArticleQuietly();
            return false;
        }

        $orderedPageIds = DB::table('keyword_page')
            ->selectRaw('page_id, MIN(COALESCE(position, 2147483647)) as best_position')
            ->whereIn('keyword_id', $researchedKeywords)
            ->groupBy('page_id')
            ->orderBy('best_position')
            ->limit($this->getResearchExtractionPageLimit())
            ->pluck('page_id')
            ->all();

        if ($orderedPageIds === []) {
            $researchData->setPagePointExtractionCompleted(true);
            $this->touchArticleQuietly();
            return false;
        }

        $pagesById = Page::query()
            ->whereIn('id', $orderedPageIds)
            ->get()
            ->keyBy('id');

        foreach ($orderedPageIds as $pageId) {
            $page = $pagesById->get($pageId);
            if (! $page instanceof Page) {
                continue;
            }

            $canonicalUrl = UrlNormalizer::normalize((string) $page->url);
            if ($canonicalUrl === '') {
                continue;
            }

            if (array_key_exists($canonicalUrl, $researchData->getPointsByPageUrl())) {
                continue;
            }

            $content = $this->buildResearchContentFromPage($page);
            if ($content === '') {
                $researchData->setPagePoints((string) $page->url, []);
                $this->touchArticleQuietly();
                continue;
            }

            $researcher = $this->synthesizer()->getResearcher();
            $points = $researcher->extractIdeaPoints($pickedIdea, $content);

            $researchData->setPagePoints(
                (string) $page->url,
                $points
            );

            $this->touchArticleQuietly();

            return true;
        }

        $researchData->setPagePointExtractionCompleted(true);
        $this->touchArticleQuietly();

        return false;
    }

    protected function getResearchExtractionPageLimit(): int
    {
        return max(1, (int) config('synthesizer.researcher.max_pages', 20));
    }

    protected function buildResearchContentFromPage(Page $page): string
    {
        // Build a compact text bundle for the researcher from page metadata/embedding text.
        $chunks = array_filter([
            $page->title !== null ? trim((string) $page->title) : '',
            $page->description !== null ? trim((string) $page->description) : '',
            $page->getTextForEmbedding() !== null ? trim((string) $page->getTextForEmbedding()) : '',
            trim((string) $page->url),
        ], static fn (string $value): bool => $value !== '');

        return trim(implode("\n\n", array_unique($chunks)));
    }
}
