<?php

namespace App\Contracts\Model\Article\StageData;

use App\Concerns\Serializable;
use App\Contracts\CommonData\Keyword;
use App\Contracts\Synthesizer\Researcher\ConflictedPoints;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Utils\UrlNormalizer;

final class ResearchStageData implements \App\Contracts\Serializable
{
    use Serializable;

    // Keyword inputs inferred from the picked idea and tracked across stage checkpoints.
    /** @var Keyword[] */
    protected array $keywords = [];

    /**
     * Extracted researcher output grouped by canonical normalized page URL.
     *
     * @var array<string, RelevantPoint[]>
     */
    protected array $pointsByPageUrl = [];

    /**
     * True when extraction has scanned all candidate pages for current keywords.
     */
    protected bool $isPagePointExtractionCompleted = false;

    /**
     * Consolidated central points across all researched pages.
     *
     * @var RelevantPoint[]
     */
    protected array $points = [];

    /**
     * Conflicts discovered while consolidating points.
     *
     * @var ConflictedPoints[]
     */
    protected array $conflicts = [];

    /**
     * Points resolved from conflicts and waiting for final consolidation pass.
     *
     * @var RelevantPoint[]
     */
    protected array $resolvedConflictedPoints = [];

    /**
     * Conflicts that could not be resolved with high-confidence facts.
     *
     * @var ConflictedPoints[]
     */
    protected array $unresolvableConflicts = [];


    /**
     * @return Keyword[]
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * @param  iterable<Keyword>  $keywords
     */
    public function setKeywords(iterable $keywords): static
    {
        $previous = json_encode(array_map(
            static fn (Keyword $keyword): array => $keyword->toArray(),
            $this->keywords
        ), JSON_UNESCAPED_UNICODE);
        $normalized = [];
        $seen = [];

        foreach ($keywords as $keyword) {
            if (! $keyword instanceof Keyword) {
                continue;
            }

            $key = json_encode($keyword->toArray(), JSON_UNESCAPED_UNICODE);
            if ($key === false || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $keyword;
        }

        $this->keywords = array_values($normalized);
        $current = json_encode(array_map(
            static fn (Keyword $keyword): array => $keyword->toArray(),
            $this->keywords
        ), JSON_UNESCAPED_UNICODE);

        if ($previous !== $current) {
            $this->isPagePointExtractionCompleted = false;
        }

        return $this;
    }

    public function hasKeywords(): bool
    {
        return $this->keywords !== [];
    }

    /** @return array<string, RelevantPoint[]> */
    public function getPointsByPageUrl(): array
    {
        return $this->pointsByPageUrl;
    }

    public function hasPointsByPageUrl(): bool
    {
        return $this->pointsByPageUrl !== [];
    }

    public function isPagePointExtractionCompleted(): bool
    {
        return $this->isPagePointExtractionCompleted;
    }

    public function setPagePointExtractionCompleted(bool $isCompleted): static
    {
        $this->isPagePointExtractionCompleted = $isCompleted;

        return $this;
    }

    /**
     * @param  RelevantPoint[]  $points
     */
    public function setPagePoints(string $url, array $points): static
    {
        $canonicalUrl = UrlNormalizer::normalize($url);
        if ($canonicalUrl === '') {
            return $this;
        }

        $normalizedPoints = [];
        foreach ($points as $point) {
            if ($point instanceof RelevantPoint) {
                $normalizedPoints[] = $point;
                continue;
            }

            if ($point instanceof \App\Contracts\CommonData\Point) {
                $normalizedPoints[] = RelevantPoint::fromArray($point->toArray());
            }
        }

        $this->pointsByPageUrl[$canonicalUrl] = array_values($normalizedPoints);

        return $this;
    }

    /**
     * @return RelevantPoint[]
     */
    public function getPoints(): array
    {
        return $this->points;
    }

    /**
     * @param  RelevantPoint[]  $points
     */
    public function setPoints(array $points): static
    {
        $normalizedPoints = [];
        foreach ($points as $point) {
            if ($point instanceof RelevantPoint) {
                $normalizedPoints[] = $point;
                continue;
            }

            if ($point instanceof \App\Contracts\CommonData\Point) {
                $normalizedPoints[] = RelevantPoint::fromArray($point->toArray());
            }
        }

        $this->points = array_values($normalizedPoints);

        return $this;
    }

    /**
     * @return ConflictedPoints[]
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    /**
     * @param  ConflictedPoints[]  $conflicts
     */
    public function setConflicts(array $conflicts): static
    {
        $normalizedConflicts = [];
        foreach ($conflicts as $conflict) {
            if ($conflict instanceof ConflictedPoints) {
                $normalizedConflicts[] = $conflict;
            }
        }

        $this->conflicts = array_values($normalizedConflicts);

        return $this;
    }

    public function shiftConflict(): ?ConflictedPoints
    {
        return array_shift($this->conflicts);
    }

    /**
     * @return RelevantPoint[]
     */
    public function getResolvedConflictedPoints(): array
    {
        return $this->resolvedConflictedPoints;
    }

    /**
     * @param  RelevantPoint[]  $points
     */
    public function setResolvedConflictedPoints(array $points): static
    {
        $normalizedPoints = [];
        foreach ($points as $point) {
            if ($point instanceof RelevantPoint) {
                $normalizedPoints[] = $point;
            }
        }

        $this->resolvedConflictedPoints = array_values($normalizedPoints);

        return $this;
    }

    public function addResolvedConflictedPoint(RelevantPoint $point): static
    {
        $points = $this->getResolvedConflictedPoints();
        $points[] = $point;

        return $this->setResolvedConflictedPoints($points);
    }

    /**
     * @return ConflictedPoints[]
     */
    public function getUnresolvableConflicts(): array
    {
        return $this->unresolvableConflicts;
    }

    /**
     * @param  ConflictedPoints[]  $conflicts
     */
    public function setUnresolvableConflicts(array $conflicts): static
    {
        $normalizedConflicts = [];
        foreach ($conflicts as $conflict) {
            if ($conflict instanceof ConflictedPoints) {
                $normalizedConflicts[] = $conflict;
            }
        }

        $this->unresolvableConflicts = array_values($normalizedConflicts);

        return $this;
    }

    public function addUnresolvableConflict(ConflictedPoints $conflict): static
    {
        $conflicts = $this->getUnresolvableConflicts();
        $conflicts[] = $conflict;

        return $this->setUnresolvableConflicts($conflicts);
    }

    public function removePagePoints(string $url): static
    {
        $canonicalUrl = UrlNormalizer::normalize($url);
        if ($canonicalUrl === '') {
            return $this;
        }

        unset($this->pointsByPageUrl[$canonicalUrl]);

        return $this;
    }

    public function toArray(): array
    {
        return [
            'keywords' => array_map(static fn (Keyword $keyword): array => $keyword->toArray(), $this->getKeywords()),
            'is_page_point_extraction_completed' => $this->isPagePointExtractionCompleted(),
            'points_by_page_url' => array_map(
                static fn (array $points): array => array_map(
                    static fn (RelevantPoint $point): array => $point->toArray(),
                    $points
                ),
                $this->getPointsByPageUrl()
            ),
            'points' => array_map(
                static fn (RelevantPoint $point): array => $point->toArray(),
                $this->getPoints()
            ),
            'conflicts' => array_map(
                static fn (ConflictedPoints $conflict): array => $conflict->toArray(),
                $this->getConflicts()
            ),
            'resolved_conflicted_points' => array_map(
                static fn (RelevantPoint $point): array => $point->toArray(),
                $this->getResolvedConflictedPoints()
            ),
            'unresolvable_conflicts' => array_map(
                static fn (ConflictedPoints $conflict): array => $conflict->toArray(),
                $this->getUnresolvableConflicts()
            ),
        ];
    }

    public static function fromArray(array $data): static
    {
        $dto = new static;

        if (isset($data['keywords']) && is_array($data['keywords'])) {
            $keywords = [];
            foreach ($data['keywords'] as $item) {
                if ($item instanceof Keyword) {
                    $keywords[] = $item;
                    continue;
                }

                if (is_array($item)) {
                    try {
                        $keywords[] = Keyword::fromArray($item);
                    } catch (\Throwable) {
                        continue;
                    }
                }
            }
            $dto->setKeywords($keywords);
        }

        if (array_key_exists('is_page_point_extraction_completed', $data)) {
            $dto->setPagePointExtractionCompleted((bool) $data['is_page_point_extraction_completed']);
        }

        if (isset($data['points_by_page_url']) && is_array($data['points_by_page_url'])) {
            foreach ($data['points_by_page_url'] as $url => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $points = [];
                foreach ($item as $pointRow) {
                    if ($pointRow instanceof RelevantPoint) {
                        $points[] = $pointRow;
                        continue;
                    }

                    if (is_array($pointRow)) {
                        $points[] = RelevantPoint::fromArray($pointRow);
                    }
                }
                $dto->setPagePoints((string) $url, $points);
            }
        }

        if (isset($data['points']) && is_array($data['points'])) {
            $points = [];
            foreach ($data['points'] as $row) {
                if ($row instanceof RelevantPoint) {
                    $points[] = $row;
                    continue;
                }

                if (is_array($row)) {
                    $points[] = RelevantPoint::fromArray($row);
                }
            }
            $dto->setPoints($points);
        }

        if (isset($data['conflicts']) && is_array($data['conflicts'])) {
            $conflicts = [];
            foreach ($data['conflicts'] as $row) {
                if ($row instanceof ConflictedPoints) {
                    $conflicts[] = $row;
                    continue;
                }

                if (is_array($row)) {
                    $conflicts[] = ConflictedPoints::fromArray($row);
                }
            }
            $dto->setConflicts($conflicts);
        }

        if (isset($data['resolved_conflicted_points']) && is_array($data['resolved_conflicted_points'])) {
            $resolvedPoints = [];
            foreach ($data['resolved_conflicted_points'] as $row) {
                if ($row instanceof RelevantPoint) {
                    $resolvedPoints[] = $row;
                    continue;
                }

                if (is_array($row)) {
                    $resolvedPoints[] = RelevantPoint::fromArray($row);
                }
            }
            $dto->setResolvedConflictedPoints($resolvedPoints);
        }

        if (isset($data['unresolvable_conflicts']) && is_array($data['unresolvable_conflicts'])) {
            $unresolvableConflicts = [];
            foreach ($data['unresolvable_conflicts'] as $row) {
                if ($row instanceof ConflictedPoints) {
                    $unresolvableConflicts[] = $row;
                    continue;
                }

                if (is_array($row)) {
                    $unresolvableConflicts[] = ConflictedPoints::fromArray($row);
                }
            }
            $dto->setUnresolvableConflicts($unresolvableConflicts);
        }

        return $dto;
    }
}
