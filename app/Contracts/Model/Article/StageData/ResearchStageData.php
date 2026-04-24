<?php

namespace App\Contracts\Model\Article\StageData;

use App\Concerns\Serializable;
use App\Contracts\CommonData\Keyword;
use App\Contracts\CommonData\Point;
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
     * @var array<string, Point[]>
     */
    protected array $pointsByPageUrl = [];

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

    /** @return array<string, Point[]> */
    public function getPointsByPageUrl(): array
    {
        return $this->pointsByPageUrl;
    }

    public function hasPointsByPageUrl(): bool
    {
        return $this->pointsByPageUrl !== [];
    }

    /**
     * @param  Point[]  $points
     */
    public function setPagePoints(string $url, array $points): static
    {
        $canonicalUrl = UrlNormalizer::normalize($url);
        if ($canonicalUrl === '') {
            return $this;
        }

        $normalizedPoints = [];
        foreach ($points as $point) {
            if ($point instanceof Point) {
                $normalizedPoints[] = $point;
            }
        }

        $this->pointsByPageUrl[$canonicalUrl] = array_values($normalizedPoints);

        return $this;
    }

    public function toArray(): array
    {
        return [
            'keywords' => array_map(static fn (Keyword $keyword): array => $keyword->toArray(), $this->getKeywords()),
            'points_by_page_url' => array_map(
                static fn (array $points): array => array_map(
                    static fn (Point $point): array => $point->toArray(),
                    $points
                ),
                $this->getPointsByPageUrl()
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
                    if ($pointRow instanceof Point) {
                        $points[] = $pointRow;
                        continue;
                    }

                    if (is_array($pointRow)) {
                        $points[] = Point::fromArray($pointRow);
                    }
                }
                $dto->setPagePoints((string) $url, $points);
            }
        }

        return $dto;
    }
}
