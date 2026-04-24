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

        return $dto;
    }
}
