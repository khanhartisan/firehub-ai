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
        $mergeProgress = $this->processIntentMerging();
        if ($mergeProgress !== true) {
            return $mergeProgress;
        }

        // 5) Filter uniqueness
        $uniquenessProgress = $this->processUniquenessChecks();
        if ($uniquenessProgress !== true) {
            return $uniquenessProgress;
        }

        // 6) Audit surviving ideas.
        $auditProgress = $this->processAudits();
        if ($auditProgress !== true) {
            return $auditProgress;
        }

        $ideaData = $this->getIdeaStageData();
        $ideaAuditReports = $ideaData->getAuditReports();

        if ($ideaData->getPickedReport() instanceof IdeaAuditReport) {
            return true;
        }

        // 7) Persist picker output first, then finalize picked report on next run.
        if (! $ideaData->hasPickedReports()) {
            $pickedReports = $this
                ->getIdeaForgeService()
                ->getIdeaPicker()
                ->pick($ideaAuditReports, $context, 1)
                ?? [];
            if ($pickedReports === []) {
                return false;
            }

            $ideaData->setPickedReports($pickedReports);
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

    protected function processIntentMerging(): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        $allIdeas = [];
        foreach ($ideaData->getAdvisorDataMap() as $advisorData) {
            $allIdeas = [...$allIdeas, ...$advisorData->getIdeas()];
        }
        $allIdeas = array_values(array_filter($allIdeas, static fn ($idea): bool => $idea instanceof Idea));

        if ($allIdeas === []) {
            return false;
        }

        $ideas = $ideaData->getIdeas();
        if ($ideas === []) {
            $ideas = $allIdeas;
        }

        $ideaMap = $this->buildIdeaMap($ideas);
        if ($ideaMap === []) {
            return false;
        }

        $possiblePairs = $this->buildUniqueMergePairs($ideaMap);
        if ($possiblePairs === []) {
            $ideaData->setIdeas(array_values($ideaMap));
            $ideaData->setUniqueIdeaIdentifierPairs([]);
            $this->touchArticleQuietly();

            return true;
        }

        $uniquePairs = $this->cleanPairs($ideaData->getUniqueIdeaIdentifierPairs(), $ideaMap);
        $uniquePairKeys = $this->buildPairKeyMap($uniquePairs);

        $pairToCheck = null;
        foreach ($possiblePairs as $pair) {
            $pairKey = $this->makePairKey($pair);
            if (! isset($uniquePairKeys[$pairKey])) {
                $pairToCheck = $pair;
                break;
            }
        }

        if (! is_array($pairToCheck)) {
            $ideaData->setIdeas(array_values($ideaMap));
            $ideaData->setUniqueIdeaIdentifierPairs($uniquePairs);
            $this->touchArticleQuietly();

            return true;
        }

        $pairValues = array_values($pairToCheck);
        $leftId = trim((string) ($pairValues[0] ?? ''));
        $rightId = trim((string) ($pairValues[1] ?? ''));
        if (! isset($ideaMap[$leftId], $ideaMap[$rightId])) {
            $ideaData->setIdeas(array_values($ideaMap));
            $ideaData->setUniqueIdeaIdentifierPairs($this->cleanPairs($uniquePairs, $ideaMap));
            $this->touchArticleQuietly();

            return null;
        }

        $leftIdea = $ideaMap[$leftId];
        $rightIdea = $ideaMap[$rightId];
        $mergedIntent = IntentResolver::mergeIntents($leftIdea->getIntent(), $rightIdea->getIntent());
        if ($mergedIntent) {
            $rightIdea->setIntent($mergedIntent);
            $ideaMap[$rightId] = $rightIdea;
            unset($ideaMap[$leftId]);
            // Idea graph changed; previous "already checked unique pairs" are stale.
            $uniquePairs = [];
        } else {
            $uniquePairs[] = [$leftId, $rightId];
            $uniquePairs = $this->cleanPairs($uniquePairs, $ideaMap);
        }

        $possiblePairs = $this->buildUniqueMergePairs($ideaMap);
        $uniquePairs = $this->cleanPairs($uniquePairs, $ideaMap);
        $ideaData->setIdeas(array_values($ideaMap));
        $ideaData->setUniqueIdeaIdentifierPairs($uniquePairs);
        $this->touchArticleQuietly();

        return count($uniquePairs) >= count($possiblePairs) ? true : null;
    }

    /** @param Idea[] $ideas
     *  @return array<string, Idea>
     */
    protected function buildIdeaMap(array $ideas): array
    {
        $ideaMap = [];
        foreach ($ideas as $idea) {
            if (! $idea instanceof Idea) {
                continue;
            }

            $identifier = trim((string) $idea->getIdentifier());
            if ($identifier === '') {
                continue;
            }

            $ideaMap[$identifier] = $idea;
        }

        return $ideaMap;
    }

    /**
     * @param array<string, Idea> $ideaMap
     * @return array<int, array{0: string, 1: string}>
     */
    protected function buildUniqueMergePairs(array $ideaMap): array
    {
        $identifiers = array_values(array_keys($ideaMap));
        $pairs = [];
        $count = count($identifiers);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $pairs[] = [$identifiers[$i], $identifiers[$j]];
            }
        }

        return $pairs;
    }

    /**
     * @param array<int, array<int, string>> $pairs
     * @param array<string, Idea> $ideaMap
     * @return array<int, array{0: string, 1: string}>
     */
    protected function cleanPairs(array $pairs, array $ideaMap): array
    {
        $cleaned = [];
        $seen = [];

        foreach ($pairs as $pair) {
            if (! is_array($pair)) {
                continue;
            }

            $values = array_values($pair);
            $left = trim((string) ($values[0] ?? ''));
            $right = trim((string) ($values[1] ?? ''));
            if ($left === '' || $right === '' || $left === $right) {
                continue;
            }

            if (! isset($ideaMap[$left], $ideaMap[$right])) {
                continue;
            }

            $ordered = [$left, $right];
            sort($ordered);
            $pairKey = implode('|', $ordered);
            if (isset($seen[$pairKey])) {
                continue;
            }

            $seen[$pairKey] = true;
            $cleaned[] = $ordered;
        }

        return $cleaned;
    }

    /**
     * @param array<int, array{0: string, 1: string}> $pairs
     * @return array<string, true>
     */
    protected function buildPairKeyMap(array $pairs): array
    {
        $map = [];
        foreach ($pairs as $pair) {
            $key = $this->makePairKey($pair);
            if ($key === '') {
                continue;
            }

            $map[$key] = true;
        }

        return $map;
    }

    /**
     * @param array<int, string> $pair
     */
    protected function makePairKey(array $pair): string
    {
        $values = array_values($pair);
        $left = trim((string) ($values[0] ?? ''));
        $right = trim((string) ($values[1] ?? ''));
        if ($left === '' || $right === '' || $left === $right) {
            return '';
        }

        $ordered = [$left, $right];
        sort($ordered);

        return implode('|', $ordered);
    }

    protected function processUniquenessChecks(): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        $ideaForge = $this->getIdeaForgeService();
        $ideas = $ideaData->getIdeas();
        if ($ideas === []) {
            return false;
        }

        $index = $ideaData->getUniquenessIndex();
        $remainingChecks = 20;

        while ($remainingChecks > 0 && $index < count($ideas)) {
            $idea = $ideas[$index];
            if (! $idea instanceof Idea) {
                return false;
            }

            // Remove idea in place if it fails uniqueness; keep index on same slot.
            $uniqueness = $ideaForge->getIdeaAuditor()->isIdeaUnique($this->client->id, $idea);
            if ($uniqueness->getIsUnique() === false) {
                array_splice($ideas, $index, 1);
            } else {
                $index++;
            }
            $remainingChecks--;
        }

        $ideaData->setIdeas($ideas);
        $ideaData->setUniquenessIndex($index);
        $this->touchArticleQuietly();

        return $index >= count($ideas) ? true : null;
    }

    protected function processAudits(): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        $ideaForge = $this->getIdeaForgeService();
        $ideas = $ideaData->getIdeas();
        if ($ideas === []) {
            return false;
        }

        $index = $ideaData->getAuditIndex();
        $auditReports = $ideaData->getAuditReports();
        $remainingAudits = 20;

        while ($remainingAudits > 0 && $index < count($ideas)) {
            $idea = $ideas[$index];
            if (! $idea instanceof Idea) {
                return false;
            }

            // Audit is done after uniqueness filtering to avoid unnecessary scoring.
            $auditReports[] = $ideaForge->getIdeaAuditor()->audit($idea);
            $index++;
            $remainingAudits--;
        }

        $ideaData->setAuditReports($auditReports);
        $ideaData->setAuditIndex($index);
        $this->touchArticleQuietly();

        return $index >= count($ideas) ? true : null;
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