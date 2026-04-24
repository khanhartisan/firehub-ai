<?php

namespace App\Services\Synthesizer\Researcher\Drivers;

use App\Contracts\CommonData\Point;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Services\Synthesizer\Researcher\ResearcherService;

class BasicResearcherDriver extends ResearcherService
{
    public function extractIdeaPoints(Idea $idea, string $content): array
    {
        $segments = $this->splitContentIntoSegments($content);
        $points = [];

        foreach ($segments as $segment) {
            $points[] = (new Point)
                ->setHeadline($this->makeHeadline($segment))
                ->setDescription($segment)
                ->setEvidences($this->extractEvidenceCandidates($segment));
        }

        return $points;
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

}
