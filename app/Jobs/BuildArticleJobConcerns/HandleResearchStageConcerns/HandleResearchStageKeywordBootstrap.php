<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns;

use App\Contracts\CommonData\Keyword as KeywordData;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Enums\KeywordStatus;
use App\Facades\IntentResolver;
use App\Jobs\KeywordResearchJob;
use App\Models\Keyword;
use App\Utils\Str;

trait HandleResearchStageKeywordBootstrap
{
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
