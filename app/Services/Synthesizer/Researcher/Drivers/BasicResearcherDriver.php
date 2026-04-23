<?php

namespace App\Services\Synthesizer\Researcher\Drivers;

use App\Contracts\CommonData\Point;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\IdeaPoint;
use App\Contracts\Synthesizer\Researcher\IdeaPoints;
use App\Services\Synthesizer\Researcher\ResearcherService;

class BasicResearcherDriver extends ResearcherService
{
    public function extractIdeaPoints(Idea $idea, string $content): IdeaPoints
    {
        $segments = $this->splitContentIntoSegments($content);
        $ideaPoints = [];
        $segmentCount = count($segments);

        foreach ($segments as $index => $segment) {
            $ideaPoints[] = new IdeaPoint(
                idea: $idea,
                point: (new Point)
                    ->setHeadline($this->makeHeadline($segment))
                    ->setDescription($segment)
                    ->setEvidences($this->extractEvidenceCandidates($segment)),
                relevance: $this->calculateRelevance($index, $segmentCount),
                rationale: $this->buildRationale($segment)
            );
        }

        return new IdeaPoints($idea, $ideaPoints);
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
