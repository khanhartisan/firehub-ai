<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;

/**
 * Chooses global temporal + intent picks (weighted), then brainstorms ideas per advisor for that pair.
 */
trait HandleIdeaStageBrainstorm
{
    /**
     * @return ?true if selections already exist; false if {@see selectTopSuggestions()} cannot pick;
     *         null after successfully persisting first-time selections (checkpoint before brainstorm).
     */
    protected function processTopSuggestionSelection(): ?bool
    {
        $ideaData = $this->getIdeaStageData();

        if ($ideaData->hasSelectedTemporalSuggestion()
            && $ideaData->hasSelectedIntentTypeSuggestion()
        ) {
            return true;
        }

        if (! $this->selectTopSuggestions()) {
            return false;
        }

        return null;
    }

    protected function processBrainstormCollection(string $context): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        $temporalSuggestion = $ideaData->getSelectedTemporalSuggestion();
        $intentTypeSuggestion = $ideaData->getSelectedIntentTypeSuggestion();
        if (! $temporalSuggestion || ! $intentTypeSuggestion) {
            return false;
        }

        foreach ($this->getIdeaAdvisors() as $advisor) {
            if (! $advisor instanceof IdeaAdvisor) {
                return false;
            }

            $advisorIdentifier = (string) $advisor->getIdentifier();
            $advisorData = $ideaData->getAdvisorDataByIdentifier($advisorIdentifier, true);

            // Already brainstormed for this advisor on a prior run.
            if ($advisorData->getIdeas()) {
                continue;
            }

            // One advisor per job run: persist then exit so the queue slices long advisor lists.
            $ideas = $advisor->brainstorm(
                [$temporalSuggestion],
                [$intentTypeSuggestion],
                $context,
                5
            );
            $advisorData->setIdeas($ideas);
            $this->touchArticleQuietly();

            return null;
        }

        return true;
    }

    /**
     * For each advisor, score suggestions as (confidence × advisor weight); take the best temporal
     * and the best intent independently (not necessarily from the same advisor).
     */
    protected function selectTopSuggestions(): bool
    {
        $ideaData = $this->getIdeaStageData();

        $bestTemporal = null;
        $bestTemporalWeighted = -1.0;
        $bestIntent = null;
        $bestIntentWeighted = -1.0;

        foreach ($this->getIdeaAdvisors() as $advisor) {
            if (! $advisor instanceof IdeaAdvisor) {
                continue;
            }

            $weight = $advisor->getWeight();
            $advisorData = $ideaData->getAdvisorDataByIdentifier((string) $advisor->getIdentifier());
            if (! $advisorData) {
                continue;
            }

            // Compare weighted scores across advisors for temporal…
            foreach ($advisorData->getTemporalSuggestions() as $suggestion) {
                if (! $suggestion instanceof TemporalSuggestion) {
                    continue;
                }

                $weighted = $this->weightedSuggestionScore($suggestion->getConfidence(), $weight);
                if ($weighted > $bestTemporalWeighted) {
                    $bestTemporalWeighted = $weighted;
                    $bestTemporal = $suggestion;
                }
            }

            // …and independently for intent type (winners may come from different advisors).
            foreach ($advisorData->getIntentTypeSuggestions() as $suggestion) {
                if (! $suggestion instanceof IntentTypeSuggestion) {
                    continue;
                }

                $weighted = $this->weightedSuggestionScore($suggestion->getConfidence(), $weight);
                if ($weighted > $bestIntentWeighted) {
                    $bestIntentWeighted = $weighted;
                    $bestIntent = $suggestion;
                }
            }
        }

        if (! $bestTemporal instanceof TemporalSuggestion
            || ! $bestIntent instanceof IntentTypeSuggestion
        ) {
            return false;
        }

        $ideaData->setSelectedTemporalSuggestion($bestTemporal);
        $ideaData->setSelectedIntentTypeSuggestion($bestIntent);
        $this->touchArticleQuietly();

        return true;
    }

    protected function weightedSuggestionScore(?float $confidence, float $weight): float
    {
        return ($confidence ?? 0.0) * $weight;
    }
}
