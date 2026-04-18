<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

use App\Contracts\Model\Article\StageData\IdeaStageData;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;

trait HandleIdeaStageBrainstorm
{
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

            // Skip if not empty
            if ($advisorData->getIdeas()) {
                continue;
            }

            // Same checkpoint model for brainstorm calls.
            $ideas = $advisor->brainstorm(
                [$temporalSuggestion],
                [$intentTypeSuggestion],
                $context,
                5
            );
            $ideas = array_values(array_filter($ideas, static fn ($idea): bool => $idea instanceof Idea));
            $advisorData->setIdeas($ideas);
            $this->touchArticleQuietly();

            return null;
        }

        return true;
    }

    protected function selectTopSuggestions(): bool
    {
        $ideaData = $this->getIdeaStageData();
        [$allTemporalSuggestions, $allIntentTypeSuggestions] = $this->collectAllSuggestions($ideaData);

        $selectedTemporalSuggestion = collect($allTemporalSuggestions)
            ->sortByDesc(static fn (TemporalSuggestion $suggestion): float => $suggestion->getConfidence() ?? 0.0)
            ->first();
        $selectedIntentTypeSuggestion = collect($allIntentTypeSuggestions)
            ->sortByDesc(static fn (IntentTypeSuggestion $suggestion): float => $suggestion->getConfidence() ?? 0.0)
            ->first();

        if (! $selectedTemporalSuggestion instanceof TemporalSuggestion
            || ! $selectedIntentTypeSuggestion instanceof IntentTypeSuggestion
        ) {
            return false;
        }

        $ideaData->setSelectedTemporalSuggestion($selectedTemporalSuggestion);
        $ideaData->setSelectedIntentTypeSuggestion($selectedIntentTypeSuggestion);
        $this->touchArticleQuietly();

        return true;
    }

    protected function collectAllSuggestions(IdeaStageData $ideaData): array
    {
        $allTemporalSuggestions = [];
        $allIntentTypeSuggestions = [];

        foreach ($ideaData->getAdvisorDataMap() as $savedAdvisorData) {
            $allTemporalSuggestions = [...$allTemporalSuggestions, ...$savedAdvisorData->getTemporalSuggestions()];
            $allIntentTypeSuggestions = [...$allIntentTypeSuggestions, ...$savedAdvisorData->getIntentTypeSuggestions()];
        }

        return [$allTemporalSuggestions, $allIntentTypeSuggestions];
    }
}
