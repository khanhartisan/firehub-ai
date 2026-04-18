<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

use App\Contracts\Model\Article\StageData\IdeaStageData\AdvisorData;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;

/**
 * Per-advisor temporal and intent-type suggestion calls. One network-heavy call per job tick
 * (temporal first, then intent for the same advisor) until all advisors have both lists filled.
 */
trait HandleIdeaStageSuggestionCollection
{
    /**
     * @return ?true when every advisor has temporal + intent suggestions; null after one advisor was just filled (checkpoint); false on invalid advisor.
     */
    protected function processSuggestionCollection(string $context): ?bool
    {
        $stageData = $this->getStageData();
        $ideaData = $stageData->getIdeaStageData();

        foreach ($this->getIdeaAdvisors() as $advisor) {
            if (! $advisor instanceof IdeaAdvisor) {
                return false;
            }

            $advisorIdentifier = (string) $advisor->getIdentifier();
            $advisorData = $ideaData->getAdvisorDataByIdentifier($advisorIdentifier, true);
            $this->attachAdvisorContext($advisor, $advisorData);

            // Temporal first, then intent for this advisor; only one API call total per job tick.
            if ($this->processAdvisorTemporalSuggestions($advisor, $context)
                or $this->processAdvisorIntentTypeSuggestions($advisor, $context)
            ) {
                return null;
            }
        }

        return true;
    }

    protected function attachAdvisorContext(IdeaAdvisor $advisor, AdvisorData $advisorData): void
    {
        if (method_exists($advisor, 'getDescription')) {
            $advisorData->setAdvisorDescription($advisor->getDescription());
        }
    }

    protected function processAdvisorTemporalSuggestions(
        IdeaAdvisor $advisor,
        string $context
    ): bool {
        $stageData = $this->getStageData();
        $ideaData = $stageData->getIdeaStageData();
        $advisorIdentifier = (string) $advisor->getIdentifier();
        $advisorData = $ideaData->getAdvisorDataByIdentifier($advisorIdentifier, true);

        if ($advisorData->getTemporalSuggestions()) {
            return false;
        }

        // Cold path: fetch and store temporal list, then checkpoint.
        $advisorData->setTemporalSuggestions(
            $advisor->suggestTemporal($this->client->id, $context)
        );
        $ideaData->setAdvisorDataByIdentifier($advisorIdentifier, $advisorData);
        $this->touchArticleQuietly();

        return true;
    }

    protected function processAdvisorIntentTypeSuggestions(
        IdeaAdvisor $advisor,
        string $context
    ): bool {
        $stageData = $this->getStageData();
        $ideaData = $stageData->getIdeaStageData();
        $advisorIdentifier = (string) $advisor->getIdentifier();
        $advisorData = $ideaData->getAdvisorDataByIdentifier($advisorIdentifier, true);

        if ($advisorData->getIntentTypeSuggestions()) {
            return false;
        }

        // Second call for this advisor in a separate tick after temporal exists.
        $advisorData->setIntentTypeSuggestions(
            $advisor->suggestIntentTypes($this->client->id, $context)
        );
        $ideaData->setAdvisorDataByIdentifier($advisorIdentifier, $advisorData);
        $this->touchArticleQuietly();

        return true;
    }
}
