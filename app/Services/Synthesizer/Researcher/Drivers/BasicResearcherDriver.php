<?php

namespace App\Services\Synthesizer\Researcher\Drivers;

use App\Contracts\CommonData\Fact;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\ConflictedPoints;
use App\Contracts\Synthesizer\Researcher\ConsolidationResult;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Services\Synthesizer\Researcher\ResearcherService;

class BasicResearcherDriver extends ResearcherService
{
    public function extractIdeaPoints(Idea $idea, string $content): array
    {
        $segments = $this->splitContentIntoSegments($content);
        $points = [];
        $segmentCount = count($segments);

        foreach ($segments as $index => $segment) {
            $points[] = (new RelevantPoint)
                ->setHeadline($this->makeHeadline($segment))
                ->setDescription($segment)
                ->setEvidences($this->extractEvidenceCandidates($segment))
                ->setRelevance($this->calculateRelevance($index, $segmentCount))
                ->setRationale($this->buildRationale($segment));
        }

        return $points;
    }

    public function consolidateIdeaPoints(Idea $idea, array $points): ConsolidationResult
    {
        return (new ConsolidationResult)
            ->setPoints($points);
    }

    public function resolveIdeaConflictedPoints(
        Idea $idea,
        ConflictedPoints $conflictedPoints,
        array $facts
    ): RelevantPoint {
        $fallbackPoint = $conflictedPoints->getPoints()[0] ?? new RelevantPoint;

        $evidences = [];
        foreach ($facts as $fact) {
            if ($fact instanceof Fact) {
                $evidences[] = $fact->getFact();
                continue;
            }

            if (is_array($fact) && isset($fact['fact']) && is_string($fact['fact'])) {
                $line = trim($fact['fact']);
                if ($line !== '') {
                    $evidences[] = $line;
                }
                continue;
            }

            if (is_string($fact)) {
                $line = trim($fact);
                if ($line !== '') {
                    $evidences[] = $line;
                }
            }
        }

        if ($evidences === []) {
            $evidences = $fallbackPoint->getEvidences();
        }

        return (new RelevantPoint)
            ->setHeadline($fallbackPoint->getHeadline())
            ->setDescription($fallbackPoint->getDescription())
            ->setEvidences($evidences)
            ->setRelevance($fallbackPoint->getRelevance())
            ->setRationale('Resolved from conflicted points using provided verified facts.');
    }

    /**
     * @return list<string>
     */
    protected function splitContentIntoSegments(string $content): array
    {
        $normalized = trim(preg_replace('/\r\n?/', "\n", $content) ?? '');
        if ($normalized === '') {
            return [];
        }

        $segments = preg_split('/\n\s*\n+/', $normalized) ?: [];
        $segments = array_values(array_filter(array_map(
            static fn (string $segment): string => trim(preg_replace('/\s+/', ' ', $segment) ?? ''),
            $segments
        )));

        return array_slice($segments, 0, 5);
    }

    protected function makeHeadline(string $segment): string
    {
        $sentence = preg_split('/(?<=[.!?])\s+/', trim($segment), 2)[0] ?? '';

        return mb_substr(trim($sentence), 0, 120);
    }

    /**
     * @return list<string>
     */
    protected function extractEvidenceCandidates(string $segment): array
    {
        preg_match_all('/[^.!?\n]+[.!?]?/', $segment, $matches);
        $sentences = array_values(array_filter(array_map(
            static fn (string $sentence): string => trim($sentence),
            $matches[0] ?? []
        )));

        return array_slice($sentences, 0, 3);
    }

    protected function calculateRelevance(int $index, int $segmentCount): float
    {
        if ($segmentCount <= 1) {
            return 1.0;
        }

        $step = 0.4 / max(1, $segmentCount - 1);
        $score = 1.0 - ($index * $step);

        return max(0.6, round($score, 2));
    }

    protected function buildRationale(string $segment): string
    {
        $snippet = mb_substr(trim($segment), 0, 180);

        return "This segment contains direct evidence tied to the idea context: {$snippet}";
    }
}
