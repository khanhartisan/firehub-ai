<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Facades\IntentResolver;

trait HandleIdeaStageIntentMerging
{
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
     * @return array<string, Idea>
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
}
