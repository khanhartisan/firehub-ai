<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\CommonData\Keyword as KeywordData;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Enums\KeywordStatus;
use App\Facades\IntentResolver;
use App\Jobs\KeywordResearchJob;
use App\Models\Keyword;
use App\Models\Page;
use App\Utils\Str;
use App\Utils\UrlNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HandleResearchStage
{
    protected function handleResearchStage(): ?bool
    {
        $pickedIdea = $this->getStageData()->getPickedIdea();
        if ($pickedIdea === null) {
            return false;
        }

        $bootstrapResult = $this->bootstrapResearchKeywords($pickedIdea);
        if ($bootstrapResult !== null) {
            return $bootstrapResult;
        }

        $keywords = $this->getTrackedResearchKeywords();
        if ($keywords->isEmpty()) {
            return true;
        }

        if ($this->hasPendingKeywords($keywords)) {
            return null;
        }

        $didExtractOnePage = $this->extractAndStorePointsByPage($pickedIdea, $keywords);
        if ($didExtractOnePage) {
            // Enforce one external extraction call per job run.
            return null;
        }

        return true;
    }

    /**
     * Initialize keyword tracking on first run.
     *
     * Returns:
     * - null  => bootstrap done, continue stage flow
     * - true  => no keywords inferred; stage can complete early
     * - false => hard failure (unused currently, kept for symmetry)
     */
    protected function bootstrapResearchKeywords(Idea $pickedIdea): ?bool
    {
        $researchData = $this->getStageData()->getResearchStageData();

        if ($researchData->hasKeywords()) {
            return null;
        }

        $intentKeywords = $this->guessResearchKeywords($pickedIdea);
        $keywords = [];

        foreach ($intentKeywords as $keywordData) {
            if (! $keywordData instanceof KeywordData) {
                continue;
            }
            $keywords[] = $keywordData;

            $keyword = $this->resolveOrCreateKeywordFromData($keywordData);

            if (! $keyword->status->isFinal()
                or $keyword->researched_at?->lt(now()->subDay())
            ) {
                $keyword->status = KeywordStatus::RESEARCHING;
                $keyword->save();

                KeywordResearchJob::dispatch($keyword);
            }
        }

        $researchData->setKeywords($keywords);
        $this->touchArticleQuietly();

        if ($keywords === []) {
            return true;
        }

        // Pause here so next run waits on tracked keyword statuses.
        return null;
    }

    /**
     * @return list<KeywordData>
     */
    protected function guessResearchKeywords(Idea $pickedIdea): array
    {
        try {
            $intentKeywords = IntentResolver::guessIntentKeywords($pickedIdea->getIntent());
        } catch (\Throwable) {
            $intentKeywords = [];
        }

        if (! is_iterable($intentKeywords)) {
            $intentKeywords = [];
        }

        $keywords = [];
        foreach ($intentKeywords as $intentKeyword) {
            $keyword = $intentKeyword->getKeyword();
            if ($keyword instanceof KeywordData) {
                $keywords[] = $keyword;
            }
        }

        if ($keywords !== []) {
            return $keywords;
        }

        return [];
    }

    /**
     * @return Collection<int, Keyword>
     */
    protected function getTrackedResearchKeywords(): Collection
    {
        $researchData = $this->getStageData()->getResearchStageData();
        $models = [];
        foreach ($researchData->getKeywords() as $keywordData) {
            $models[] = $this->resolveOrCreateKeywordFromData($keywordData);
        }

        return collect($models)
            ->filter(static fn ($keyword): bool => $keyword instanceof Keyword)
            ->unique(static fn (Keyword $keyword): string => (string) $keyword->id)
            ->values();
    }

    /**
     * Dispatches outstanding keyword jobs and returns true if stage should wait.
     *
     * @param  Collection<int, Keyword>  $keywords
     */
    protected function hasPendingKeywords(Collection $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (! $keyword->status->isFinal()) {
                KeywordResearchJob::dispatch($keyword);

                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Keyword>  $keywords
     */
    protected function extractAndStorePointsByPage(Idea $pickedIdea, Collection $keywords): bool
    {
        $researchData = $this->getStageData()->getResearchStageData();
        $researchedKeywords = $keywords
            ->where('status', KeywordStatus::RESEARCHED)
            ->pluck('id')
            ->all();

        if ($researchedKeywords === []) {
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

        return false;
    }

    protected function getResearchExtractionPageLimit(): int
    {
        return max(1, (int) config('synthesizer.research.max_pages', 20));
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

    protected function resolveOrCreateKeywordFromData(KeywordData $keywordData): Keyword
    {
        $keyword = new Keyword;
        $keyword->keyword = Str::sanitizeKeyword($keywordData->getKeyword());
        $keyword->language = $keywordData->getLanguage();
        $keyword->country = $keywordData->getCountry();
        $hash = $keyword->generateHash();

        $existing = Keyword::query()->where('hash', $hash)->first();
        if ($existing instanceof Keyword) {
            return $existing;
        }

        $keyword->status = KeywordStatus::PENDING;
        $keyword->save();

        return $keyword;
    }
}