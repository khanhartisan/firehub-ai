<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;

/**
 * Aggregates weighted temporal + intent suggestion lists, then brainstorms per advisor.
 */
trait HandleIdeaStageBrainstorm
{
    /**
     * @return ?true if aggregated selections already exist; false if {@see selectTopSuggestions()} cannot pick;
     *         null after successfully persisting first-time aggregated selections (checkpoint before brainstorm).
     */
    protected function processTopSuggestionSelection(): ?bool
    {
        $ideaData = $this->getIdeaStageData();

        if ($ideaData->hasSelectedTemporalSuggestions()
            && $ideaData->hasSelectedIntentTypeSuggestions()
        ) {
            return true;
        }

        if (! $this->selectTopSuggestions()) {
            return false;
        }

        return null;
    }

    protected function processBrainstormCollection(SemanticContext $context): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        $temporalSuggestions = $ideaData->getSelectedTemporalSuggestions();
        $intentTypeSuggestions = $ideaData->getSelectedIntentTypeSuggestions();
        if ($temporalSuggestions === [] || $intentTypeSuggestions === []) {
            return false;
        }

        foreach ($this->getIdeaAdvisors() as $advisor) {
            $advisorIdentifier = (string) $advisor->getIdentifier();
            $advisorData = $ideaData->getAdvisorDataByIdentifier($advisorIdentifier, true);

            // Already brainstormed for this advisor on a prior run.
            if ($advisorData->getIdeas()) {
                continue;
            }

            // One advisor per job run: persist then exit so the queue slices long advisor lists.
            $ideas = $advisor->brainstorm(
                $temporalSuggestions,
                $intentTypeSuggestions,
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
     * For each advisor, score suggestions as (confidence × advisor weight), then persist full lists
     * sorted by weighted score descending for temporal and intent independently.
     */
    protected function selectTopSuggestions(): bool
    {
        $ideaData = $this->getIdeaStageData();

        $weightedTemporalBuckets = [];
        $weightedIntentTypeBuckets = [];
        $totalAdvisorWeight = 0.0;

        foreach ($this->getIdeaAdvisors() as $advisor) {
            if (! $advisor instanceof IdeaAdvisor) {
                continue;
            }

            $weight = $advisor->getWeight();
            $totalAdvisorWeight += $weight;
            $advisorData = $ideaData->getAdvisorDataByIdentifier((string) $advisor->getIdentifier());
            if (! $advisorData) {
                continue;
            }

            // Aggregate by unique temporal while tracking highest individual weighted contribution reason.
            foreach ($advisorData->getTemporalSuggestions() as $suggestion) {
                if (! $suggestion instanceof TemporalSuggestion) {
                    continue;
                }

                $key = $suggestion->getTemporal()->value;
                $weighted = $this->weightedSuggestionScore($suggestion->getConfidence(), $weight);
                $weightedTemporalBuckets[$key] ??= [
                    'temporal' => $suggestion->getTemporal(),
                    'weighted_sum' => 0.0,
                    'best_weighted' => -1.0,
                    'reason' => null,
                ];

                $weightedTemporalBuckets[$key]['weighted_sum'] += $weighted;
                if ($weighted > $weightedTemporalBuckets[$key]['best_weighted']) {
                    $weightedTemporalBuckets[$key]['best_weighted'] = $weighted;
                    $weightedTemporalBuckets[$key]['reason'] = $suggestion->getReason();
                }
            }

            // Aggregate by unique intent type while tracking highest individual weighted contribution reason.
            foreach ($advisorData->getIntentTypeSuggestions() as $suggestion) {
                if (! $suggestion instanceof IntentTypeSuggestion) {
                    continue;
                }

                $key = (string) $suggestion->getIntentType()->value;
                $weighted = $this->weightedSuggestionScore($suggestion->getConfidence(), $weight);
                $weightedIntentTypeBuckets[$key] ??= [
                    'intent_type' => $suggestion->getIntentType(),
                    'weighted_sum' => 0.0,
                    'best_weighted' => -1.0,
                    'reason' => null,
                ];

                $weightedIntentTypeBuckets[$key]['weighted_sum'] += $weighted;
                if ($weighted > $weightedIntentTypeBuckets[$key]['best_weighted']) {
                    $weightedIntentTypeBuckets[$key]['best_weighted'] = $weighted;
                    $weightedIntentTypeBuckets[$key]['reason'] = $suggestion->getReason();
                }
            }
        }

        if ($totalAdvisorWeight <= 0.0
            || $weightedTemporalBuckets === []
            || $weightedIntentTypeBuckets === []
        ) {
            return false;
        }

        $weightedTemporals = array_map(
            static fn (array $bucket): array => [
                'weighted' => $bucket['weighted_sum'] / $totalAdvisorWeight,
                'suggestion' => new TemporalSuggestion(
                    $bucket['temporal'],
                    $bucket['weighted_sum'] / $totalAdvisorWeight,
                    $bucket['reason']
                ),
            ],
            array_values($weightedTemporalBuckets)
        );

        $weightedIntentTypes = array_map(
            static fn (array $bucket): array => [
                'weighted' => $bucket['weighted_sum'] / $totalAdvisorWeight,
                'suggestion' => new IntentTypeSuggestion(
                    $bucket['intent_type'],
                    $bucket['weighted_sum'] / $totalAdvisorWeight,
                    $bucket['reason']
                ),
            ],
            array_values($weightedIntentTypeBuckets)
        );

        usort(
            $weightedTemporals,
            static fn (array $left, array $right): int => $right['weighted'] <=> $left['weighted']
        );
        usort(
            $weightedIntentTypes,
            static fn (array $left, array $right): int => $right['weighted'] <=> $left['weighted']
        );

        $ideaData->setSelectedTemporalSuggestions(array_map(
            static fn (array $item): TemporalSuggestion => $item['suggestion'],
            $weightedTemporals
        ));
        $ideaData->setSelectedIntentTypeSuggestions(array_map(
            static fn (array $item): IntentTypeSuggestion => $item['suggestion'],
            $weightedIntentTypes
        ));
        $this->touchArticleQuietly();

        return true;
    }

    protected function weightedSuggestionScore(?float $confidence, float $weight): float
    {
        return ($confidence ?? 0.0) * $weight;
    }
}
