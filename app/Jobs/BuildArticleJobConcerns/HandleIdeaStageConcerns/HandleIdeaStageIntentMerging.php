<?php

namespace App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns;

use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Facades\IntentResolver;

/**
 * Collapses brainstormed ideas using pairwise intent merge. State: canonical {@see IdeaStageData::getIdeas()},
 * plus {@see IdeaStageData::getUniqueIdeaIdentifierPairs()} for pairs already ruled “distinct”.
 */
trait HandleIdeaStageIntentMerging
{
    /**
     * One merge attempt (or bookkeeping) per invocation; completes when all unordered pairs are classified.
     *
     * @return ?true merge phase done; false no ideas; null checkpoint (more pairs to try or graph changed).
     */
    protected function processIntentMerging(): ?bool
    {
        $ideaData = $this->getIdeaStageData();
        // Source ideas still live per advisor until we fold them into idea.ideas.
        $allIdeas = [];
        foreach ($ideaData->getAdvisorDataMap() as $advisorData) {
            $allIdeas = [...$allIdeas, ...$advisorData->getIdeas()];
        }
        $allIdeas = array_values(array_filter($allIdeas, static fn ($idea): bool => $idea instanceof Idea));

        if ($allIdeas === []) {
            return false;
        }

        // First time through, working set is the brainstorm output; later runs reload from idea.ideas.
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
            // Single idea (or one valid id): nothing to compare.
            $ideaData->setIdeas(array_values($ideaMap));
            $ideaData->setUniqueIdeaIdentifierPairs([]);
            $this->touchArticleQuietly();

            return true;
        }

        // Pairs we already treated as "distinct" (mergeIntents returned null) — do not re-ask forever.
        $uniquePairs = $this->cleanPairs($ideaData->getUniqueIdeaIdentifierPairs(), $ideaMap);
        $uniquePairKeys = $this->buildPairKeyMap($uniquePairs);

        // Pick the first possible pair that still needs a merge decision.
        $pairToCheck = null;
        foreach ($possiblePairs as $pair) {
            $pairKey = $this->makePairKey($pair);
            if (! isset($uniquePairKeys[$pairKey])) {
                $pairToCheck = $pair;
                break;
            }
        }

        if (! is_array($pairToCheck)) {
            // Every pair is classified; merge round finished.
            $ideaData->setIdeas(array_values($ideaMap));
            $ideaData->setUniqueIdeaIdentifierPairs($uniquePairs);
            $this->touchArticleQuietly();

            return true;
        }

        $pairValues = array_values($pairToCheck);
        $leftId = trim((string) ($pairValues[0] ?? ''));
        $rightId = trim((string) ($pairValues[1] ?? ''));
        if (! isset($ideaMap[$leftId], $ideaMap[$rightId])) {
            // Stale pair after a merge removed an id; drop invalid rows and retry next tick.
            $ideaData->setIdeas(array_values($ideaMap));
            $ideaData->setUniqueIdeaIdentifierPairs($this->cleanPairs($uniquePairs, $ideaMap));
            $this->touchArticleQuietly();

            return null;
        }

        $leftIdea = $ideaMap[$leftId];
        $rightIdea = $ideaMap[$rightId];
        $mergedIntent = IntentResolver::mergeIntents($leftIdea->getIntent(), $rightIdea->getIntent());
        if ($mergedIntent) {
            // Collapse into right-hand id (left id disappears from the map).
            $rightIdea->setIntent($mergedIntent);
            $ideaMap[$rightId] = $rightIdea;
            unset($ideaMap[$leftId]);
            // Idea graph changed; previous "already checked unique pairs" are stale.
            $uniquePairs = [];
        } else {
            // Record this unordered pair as distinct so we skip it next time.
            $uniquePairs[] = [$leftId, $rightId];
            $uniquePairs = $this->cleanPairs($uniquePairs, $ideaMap);
        }

        // Recompute totals: merge may have shrunk N, changing possible pair count.
        $possiblePairs = $this->buildUniqueMergePairs($ideaMap);
        $uniquePairs = $this->cleanPairs($uniquePairs, $ideaMap);
        $ideaData->setIdeas(array_values($ideaMap));
        $ideaData->setUniqueIdeaIdentifierPairs($uniquePairs);
        $this->touchArticleQuietly();

        // Done when every surviving unordered pair is in uniquePairs (all merge decisions made).
        return count($uniquePairs) >= count($possiblePairs) ? true : null;
    }

    /**
     * Index the working idea list by non-empty `identifier` so merge steps can look up neighbors by id.
     * Later ids with the same key overwrite earlier ones (should not happen if identifiers are unique).
     *
     * @param Idea[] $ideas
     * @return array<string, Idea> identifier => idea
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
     * Every unordered pair of distinct identifiers once (nested loop: second index always after the first),
     * i.e. the full set of merge decisions
     * still outstanding until each pair is merged or marked distinct.
     *
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
     * Normalize stored pairs after merges: drop rows whose ids vanished, trim, dedupe by unordered key,
     * and skip invalid self-pairs. Output pairs are sorted `[smallerId, largerId]` for stable storage.
     *
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
     * Build a set-like map of canonical pair keys for O(1) “this unordered pair already ruled distinct”.
     *
     * @param array<int, array{0: string, 1: string}> $pairs
     * @return array<string, true> key from {@see makePairKey()} => true
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
     * Stable string for an unordered pair: sort the two ids and join with `|`, so (a,b) and (b,a) match.
     * Empty string means invalid or degenerate pair and callers should skip.
     *
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
