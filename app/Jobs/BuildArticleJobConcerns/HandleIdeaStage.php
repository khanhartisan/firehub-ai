<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Model\Article\StageData;
use App\Contracts\Model\Article\StageData\IdeaStageData;
use App\Contracts\Model\Article\StageData\IdeaStageData\AdvisorData;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;
use App\Facades\IntentResolver;
use App\Facades\Synthesizer;
use App\Models\Article;

trait HandleIdeaStage
{
    protected function handleIdeaStage(): ?bool
    {
        if (! $this->article instanceof Article) {
            return false;
        }

        $context = trim((string) $this->article->context);
        if ($context === '') {
            return false;
        }

        $ideaForge = Synthesizer::getIdeaForge();
        $stageData = $this->getStageData();
        $ideaData = $stageData->getIdeaStageData();
        $advisors = array_values($ideaForge->getIdeaAdvisors());

        $suggestionProgress = $this->processSuggestionCollection($advisors, $context, $stageData, $ideaData);
        if ($suggestionProgress !== true) {
            return $suggestionProgress;
        }

        $topSelection = $this->processTopSuggestionSelection($stageData, $ideaData);
        if ($topSelection !== true) {
            return $topSelection;
        }

        $brainstormProgress = $this->processBrainstormCollection($advisors, $context, $stageData, $ideaData);
        if ($brainstormProgress !== true) {
            return $brainstormProgress;
        }

        if ($progress = $this->processIntentMerging($stageData, $ideaData)) {
            return $progress;
        }

        if ($progress = $this->processUniquenessChecks($ideaForge, $stageData, $ideaData)) {
            return $progress;
        }

        if ($progress = $this->processAudits($ideaForge, $stageData, $ideaData)) {
            return $progress;
        }

        $ideaAuditReports = $ideaData->getAuditReports();

        if ($ideaData->getPickedReport() instanceof IdeaAuditReport) {
            return true;
        }

        if (! $ideaData->hasPickedReports()) {
            $ideaData->setPickedReports(
                $ideaForge->getIdeaPicker()->pick($ideaAuditReports, $context, 1) ?? []
            );
            $this->saveIdeaState($stageData, $ideaData);

            return null;
        }

        $pickedReports = $ideaData->getPickedReports();
        if (! $pickedReports) {
            return false;
        }

        $pickedReport = collect($pickedReports)
            ->first(fn ($report) => $report instanceof IdeaAuditReport);
        if (! $pickedReport instanceof IdeaAuditReport) {
            return false;
        }

        $ideaData->setPickedReport($pickedReport);
        $this->saveIdeaState($stageData, $ideaData);
        $this->article->temporal = $pickedReport->getIdea()->getIntent()->getTemporal();
        $this->article->save();

        return true;
    }

    protected function processSuggestionCollection(array $advisors, string $context, StageData $stageData, IdeaStageData $ideaData): ?bool
    {
        foreach ($advisors as $position => $advisor) {
            if (! $advisor instanceof IdeaAdvisor) {
                return false;
            }

            $advisorIdentifier = $this->resolveAdvisorIdentifier($advisor, $position);
            $advisorData = $ideaData->getAdvisorDataByIdentifier($advisorIdentifier);
            $this->attachAdvisorContext($advisor, $advisorData);

            if ($this->processAdvisorTemporalSuggestions($advisor, $context, $stageData, $ideaData, $advisorData, $advisorIdentifier)) {
                return null;
            }

            if ($this->processAdvisorIntentTypeSuggestions($advisor, $context, $stageData, $ideaData, $advisorData, $advisorIdentifier)) {
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
        string $context,
        StageData $stageData,
        IdeaStageData $ideaData,
        AdvisorData $advisorData,
        string $advisorIdentifier
    ): bool {
        if ($advisorData->getTemporalSuggestions() !== []) {
            return false;
        }

        $advisorData->setTemporalSuggestions(
            $advisor->suggestTemporal($this->client->id, $context)
        );
        $this->saveAdvisorState($stageData, $ideaData, $advisorData, $advisorIdentifier);

        return true;
    }

    protected function processAdvisorIntentTypeSuggestions(
        IdeaAdvisor $advisor,
        string $context,
        StageData $stageData,
        IdeaStageData $ideaData,
        AdvisorData $advisorData,
        string $advisorIdentifier
    ): bool {
        if ($advisorData->getIntentTypeSuggestions() !== []) {
            return false;
        }

        $advisorData->setIntentTypeSuggestions(
            $advisor->suggestIntentTypes($this->client->id, $context)
        );
        $this->saveAdvisorState($stageData, $ideaData, $advisorData, $advisorIdentifier);

        return true;
    }

    protected function processTopSuggestionSelection(StageData $stageData, IdeaStageData $ideaData): ?bool
    {
        if ($ideaData->hasSelectedTemporalSuggestion()
            && $ideaData->hasSelectedIntentTypeSuggestion()
        ) {
            return true;
        }

        if (! $this->selectTopSuggestions($stageData, $ideaData)) {
            return false;
        }

        return null;
    }

    protected function processBrainstormCollection(array $advisors, string $context, StageData $stageData, IdeaStageData $ideaData): ?bool
    {
        $temporalSuggestion = $ideaData->getSelectedTemporalSuggestion();
        $intentTypeSuggestion = $ideaData->getSelectedIntentTypeSuggestion();
        if (! $temporalSuggestion || ! $intentTypeSuggestion) {
            return false;
        }

        foreach ($advisors as $position => $advisor) {
            if (! $advisor instanceof IdeaAdvisor) {
                return false;
            }

            $advisorIdentifier = $this->resolveAdvisorIdentifier($advisor, $position);
            $advisorData = $ideaData->getAdvisorDataByIdentifier($advisorIdentifier);
            if ($advisorData->getIdeas() !== []) {
                continue;
            }

            $advisorData->setIdeas(
                $advisor->brainstorm([$temporalSuggestion], [$intentTypeSuggestion], $context, 5)
            );
            $this->saveAdvisorState($stageData, $ideaData, $advisorData, $advisorIdentifier);

            return null;
        }

        return true;
    }

    protected function processIntentMerging(StageData $stageData, IdeaStageData $ideaData): ?bool
    {
        $allIdeas = [];
        foreach ($ideaData->getAdvisorDataMap() as $advisorData) {
            $allIdeas = [...$allIdeas, ...$advisorData->getIdeas()];
        }
        if ($allIdeas === []) {
            return false;
        }

        $candidateIndex = $ideaData->getMergeCandidateIndex();
        $compareIndex = $ideaData->getMergeCompareIndex();
        $mergedIdeas = $ideaData->getMergedIdeas();

        if ($candidateIndex >= count($allIdeas)) {
            return null;
        }

        $candidate = $allIdeas[$candidateIndex];
        if (! $candidate instanceof Idea) {
            return false;
        }

        if ($compareIndex < count($mergedIdeas)) {
            $mergedIdea = $mergedIdeas[$compareIndex];
            if (! $mergedIdea instanceof Idea) {
                return false;
            }

            $mergedIntent = IntentResolver::mergeIntents($candidate->getIntent(), $mergedIdea->getIntent());
            if ($mergedIntent) {
                $mergedIdea->setIntent($mergedIntent);
                $mergedIdeas[$compareIndex] = $mergedIdea;
                $ideaData->setMergedIdeas($mergedIdeas);
                $ideaData->setMergeCandidateIndex($candidateIndex + 1);
                $ideaData->setMergeCompareIndex(0);
            } else {
                $ideaData->setMergeCompareIndex($compareIndex + 1);
            }
            $this->saveIdeaState($stageData, $ideaData);
            return null;
        }

        $mergedIdeas[] = $candidate;
        $ideaData->setMergedIdeas($mergedIdeas);
        $ideaData->setMergeCandidateIndex($candidateIndex + 1);
        $ideaData->setMergeCompareIndex(0);
        $this->saveIdeaState($stageData, $ideaData);

        return null;
    }

    protected function processUniquenessChecks(mixed $ideaForge, StageData $stageData, IdeaStageData $ideaData): ?bool
    {
        $mergedIdeas = $ideaData->getMergedIdeas();
        if ($mergedIdeas === []) {
            return false;
        }

        $index = $ideaData->getUniquenessIndex();
        if ($index >= count($mergedIdeas)) {
            return null;
        }

        $idea = $mergedIdeas[$index];
        if (! $idea instanceof Idea) {
            return false;
        }

        $uniqueness = $ideaForge->getIdeaAuditor()->isIdeaUnique($this->client->id, $idea);
        if ($uniqueness->getIsUnique() !== false) {
            $uniqueIdeas = $ideaData->getUniqueIdeas();
            $uniqueIdeas[] = $idea;
            $ideaData->setUniqueIdeas($uniqueIdeas);
        }

        $ideaData->setUniquenessIndex($index + 1);
        $this->saveIdeaState($stageData, $ideaData);

        return null;
    }

    protected function processAudits(mixed $ideaForge, StageData $stageData, IdeaStageData $ideaData): ?bool
    {
        $uniqueIdeas = $ideaData->getUniqueIdeas();
        if ($uniqueIdeas === []) {
            return false;
        }

        $index = $ideaData->getAuditIndex();
        if ($index >= count($uniqueIdeas)) {
            return null;
        }

        $idea = $uniqueIdeas[$index];
        if (! $idea instanceof Idea) {
            return false;
        }

        $auditReports = $ideaData->getAuditReports();
        $auditReports[] = $ideaForge->getIdeaAuditor()->audit($idea);
        $ideaData->setAuditReports($auditReports);
        $ideaData->setAuditIndex($index + 1);
        $this->saveIdeaState($stageData, $ideaData);

        return null;
    }

    protected function selectTopSuggestions(StageData $stageData, IdeaStageData $ideaData): bool
    {
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
        $this->saveIdeaState($stageData, $ideaData);

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

    protected function getStageData(): StageData
    {
        if ($this->article->stage_data instanceof StageData) {
            return $this->article->stage_data;
        }

        return StageData::fromArray([]);
    }

    protected function getIdeaData(StageData $stageData): IdeaStageData
    {
        return $stageData->getIdeaStageData();
    }

    protected function saveAdvisorState(
        StageData $stageData,
        IdeaStageData $ideaData,
        AdvisorData $advisorData,
        string $advisorIdentifier
    ): void {
        $ideaData->setAdvisorDataByIdentifier($advisorIdentifier, $advisorData);
        $this->saveIdeaState($stageData, $ideaData);
    }

    protected function resolveAdvisorIdentifier(IdeaAdvisor $advisor, int $position): string
    {
        $identifier = $advisor->getIdentifier();
        if (! is_string($identifier) || trim($identifier) === '') {
            throw new \RuntimeException(sprintf(
                'Idea advisor at position %d (%s) is missing required identifier.',
                $position,
                $advisor::class
            ));
        }

        return $identifier;
    }

    protected function saveIdeaState(StageData $stageData, IdeaStageData $ideaData): void
    {
        $stageData->setIdeaStageData($ideaData);
        $this->article->stage_data = $stageData;
        $this->article->save();
    }
}