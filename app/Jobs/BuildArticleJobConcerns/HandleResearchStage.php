<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\CommonData\Keyword as KeywordData;
use App\Contracts\Model\Article\StageData\ResearchStageData;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Enums\KeywordStatus;
use App\Facades\IntentResolver;
use App\Jobs\KeywordResearchJob;
use App\Models\Keyword;
use App\Models\Page;
use App\Utils\Str;
use Illuminate\Support\Collection;

trait HandleResearchStage
{
    protected function handleResearchStage(): ?bool
    {
        $pickedIdea = $this->getStageData()->getPickedIdea();
        if ($pickedIdea === null) {
            return false;
        }

        $researchData = $this->getStageData()->getResearchStageData();

        $bootstrapResult = $this->bootstrapResearchKeywords($pickedIdea, $researchData);
        if ($bootstrapResult !== null) {
            return $bootstrapResult;
        }

        $keywords = $this->getTrackedResearchKeywords($researchData);
        if ($keywords->isEmpty()) {
            return true;
        }

        if ($this->hasPendingKeywords($keywords)) {
            return null;
        }

        if ($researchData->hasPointsByPageUrl()) {
            return true;
        }

        $this->extractAndStorePointsByPage($pickedIdea, $researchData, $keywords);

        $this->touchArticleQuietly();

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
    protected function bootstrapResearchKeywords(Idea $pickedIdea, ResearchStageData $researchData): ?bool
    {
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

            try {
                $keyword = $this->resolveOrCreateKeywordFromData($keywordData);
            } catch (\Throwable) {
                continue;
            }
            if (! $keyword->status->isFinal()) {
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
    protected function getTrackedResearchKeywords(ResearchStageData $researchData): Collection
    {
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
    protected function extractAndStorePointsByPage(Idea $pickedIdea, ResearchStageData $researchData, Collection $keywords): void
    {
        $researchedKeywords = $keywords
            ->where('status', KeywordStatus::RESEARCHED)
            ->pluck('id')
            ->all();

        if ($researchedKeywords === []) {
            return;
        }

        $pages = Page::query()
            ->whereHas('keywords', function ($query) use ($researchedKeywords): void {
                $query->whereIn('keywords.id', $researchedKeywords);
            })
            ->get();

        foreach ($pages as $page) {
            $content = $this->buildResearchContentFromPage($page);
            if ($content === '') {
                continue;
            }

            $researcher = $this->synthesizer()->getResearcher();
            try {
                $ideaPoints = $researcher->extractPoints($pickedIdea, $content);
            } catch (\Throwable) {
                continue;
            }

            $researchData->setPageIdeaPoints(
                (string) $page->url,
                $ideaPoints
            );
        }
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