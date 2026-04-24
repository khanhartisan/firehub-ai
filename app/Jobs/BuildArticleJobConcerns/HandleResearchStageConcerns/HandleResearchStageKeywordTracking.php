<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleResearchStageConcerns;

use App\Jobs\KeywordResearchJob;
use App\Models\Keyword;
use Illuminate\Support\Collection;

trait HandleResearchStageKeywordTracking
{
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
}
