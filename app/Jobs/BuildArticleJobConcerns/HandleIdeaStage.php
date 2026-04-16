<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Model\Article\StageData;
use App\Contracts\Model\Article\StageData\IdeaStageData;
use App\Contracts\Model\Article\StageData\IdeaStageData\AdvisorData;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\IdeaForge;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;
use App\Enums\ArticleStatus;
use App\Facades\IntentResolver;
use App\Facades\Synthesizer;
use App\Models\Article;
use App\Utils\Str;

trait HandleIdeaStage
{
    /** @var IdeaAdvisor[]|null */
    protected ?array $resolvedIdeaAdvisors = null;
    protected ?IdeaForge $resolvedIdeaForge = null;

    /**
     * @throws \Exception
     */
    protected function handleIdeaStage(): ?bool
    {
        if (! $this->article instanceof Article) {
            return false;
        }

        // Common context
        $clientContext = trim($this->client->context);
        $articleContext = trim($this->article->context);
        $context = $clientContext."\n---\n".$articleContext;
        if (!$clientContext and !$articleContext) {
            return false;
        }

        // Get latest posts
        $latestArticles = $this->client
            ->articles()
            ->take(1000)
            ->orderByDesc('id')
            ->get()
            ->filter(function (Article $article) {
                return !!$article->title;
            });

        // Idea brainstorm context
        $ideaBrainstormContext = $context
        ."\n---\n Below is the list of the latest articles:\n"
        .$latestArticles
            ->map(function (Article $article) {
                return Str::limit($article->title, 160);
            })->join("\n- ");

        // 1) Collect advisor suggestions.
        // Each costly step checkpoints and exits for re-execution.
        $suggestionProgress = $this->processSuggestionCollection($ideaBrainstormContext);
        if ($suggestionProgress !== true) {
            return $suggestionProgress;
        }

        // 2) Pick one highest-confidence temporal + intent suggestion globally.
        $topSelection = $this->processTopSuggestionSelection();
        if ($topSelection !== true) {
            return $topSelection;
        }

        // 3) Brainstorm per advisor using the selected temporal + intent pair.
        $brainstormProgress = $this->processBrainstormCollection($ideaBrainstormContext);
        if ($brainstormProgress !== true) {
            return $brainstormProgress;
        }

        // 4) Merge similar intents
        if ($progress = $this->processIntentMerging()) {
            return $progress;
        }

        // 5) Filter uniqueness
        if ($progress = $this->processUniquenessChecks()) {
            return $progress;
        }

        // 6) Audit surviving ideas.
        if ($progress = $this->processAudits()) {
            return $progress;
        }

        $ideaData = $this->getIdeaStageData();
        $ideaAuditReports = $ideaData->getAuditReports();

        if ($ideaData->getPickedReport() instanceof IdeaAuditReport) {
            return true;
        }

        // 7) Persist picker output first, then finalize picked report on next run.
        if (! $ideaData->hasPickedReports()) {
            $ideaData->setPickedReports(
                $this
                    ->getIdeaForgeService()
                    ->getIdeaPicker()
                    ->pick($ideaAuditReports, $context, 1)
                    ?? []
            );
            $this->touchArticleQuietly();

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
        $this->touchArticleQuietly();
        $this->article->temporal = $pickedReport
            ->getIdea()
            ->getIntent()
            ->getTemporal();
        $this->touchArticleQuietly();

        return true;
    }

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
            $advisorData->setIdeas(
                $advisor->brainstorm([$temporalSuggestion], [$intentTypeSuggestion], $context, 5)
            );
            $this->touchArticleQuietly();

            return null;
        }

        return true;
    }

    protected function processIntentMerging(): ?bool
    {
        $ideaData = $this->getIdeaStageData();
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

        // Try merging candidate with existing merged ideas before appending as new.
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
            $this->touchArticleQuietly();
            return null;
        }

        $mergedIdeas[] = $candidate;
        $ideaData->setMergedIdeas($mergedIdeas);
        $ideaData->setMergeCandidateIndex($candidateIndex + 1);
        $ideaData->setMergeCompareIndex(0);
        $this->touchArticleQuietly();

        return null;
    }

    protected function processUniquenessChecks(): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        $ideaForge = $this->getIdeaForgeService();
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

        // Only keep ideas that pass uniqueness screening.
        $uniqueness = $ideaForge->getIdeaAuditor()->isIdeaUnique($this->client->id, $idea);
        if ($uniqueness->getIsUnique() !== false) {
            $uniqueIdeas = $ideaData->getUniqueIdeas();
            $uniqueIdeas[] = $idea;
            $ideaData->setUniqueIdeas($uniqueIdeas);
        }

        $ideaData->setUniquenessIndex($index + 1);
        $this->touchArticleQuietly();

        return null;
    }

    protected function processAudits(): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        $ideaForge = $this->getIdeaForgeService();
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

        // Audit is done after uniqueness filtering to avoid unnecessary scoring.
        $auditReports = $ideaData->getAuditReports();
        $auditReports[] = $ideaForge->getIdeaAuditor()->audit($idea);
        $ideaData->setAuditReports($auditReports);
        $ideaData->setAuditIndex($index + 1);
        $this->touchArticleQuietly();

        return null;
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

    protected function getIdeaStageData(): IdeaStageData
    {
        return $this->getStageData()->getIdeaStageData();
    }

    /** @return IdeaAdvisor[] */
    protected function getIdeaAdvisors(): array
    {
        if (is_array($this->resolvedIdeaAdvisors)) {
            return $this->resolvedIdeaAdvisors;
        }

        $this->resolvedIdeaAdvisors = array_values($this->getIdeaForgeService()->getIdeaAdvisors());

        return $this->resolvedIdeaAdvisors;
    }

    protected function getIdeaForgeService(): IdeaForge
    {
        return $this->resolvedIdeaForge ??= Synthesizer::getIdeaForge();
    }

    protected function getStageData(): StageData
    {
        if ($this->article->stage_data instanceof StageData) {
            return $this->article->stage_data;
        }

        $this->article->stage_data = StageData::fromArray([]);

        return $this->article->stage_data;
    }

}