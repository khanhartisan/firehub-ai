<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

use App\Contracts\Model\Article\StageData\IdeaStageData\AdvisorData;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;

trait HandleIdeaStageSuggestionCollection
{
    /**
     * @param string $context
     * @return bool|null
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

            // Bound runtime: do a single external call per execution, persist, then return.
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

        $advisorData->setIntentTypeSuggestions(
            $advisor->suggestIntentTypes($this->client->id, $context)
        );
        $ideaData->setAdvisorDataByIdentifier($advisorIdentifier, $advisorData);
        $this->touchArticleQuietly();

        return true;
    }
}
