<?php

namespace App\Contracts\Synthesizer\OutlineBuilder;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;

/**
 * One node in a synthesizer outline backed by a primary point and optional sub-points.
 */
final class OutlineItem implements Serializable
{
    use SerializableTrait;

    protected RelevantPoint $point;

    /**
     * @var RelevantPoint[]
     */
    protected array $subPoints = [];

    /**
     * @var string[]
     */
    protected array $guidelines = [];

    public function __construct()
    {
        $this->point = new RelevantPoint;
    }

    public function getPoint(): RelevantPoint
    {
        return $this->point;
    }

    public function setPoint(RelevantPoint $point): static
    {
        $this->point = $point;

        return $this;
    }

    /**
     * @return RelevantPoint[]
     */
    public function getSubPoints(): array
    {
        return $this->subPoints;
    }

    public function addSubPoint(RelevantPoint $point): static
    {
        $this->subPoints[] = $point;

        return $this;
    }

    /**
     * @param  RelevantPoint[]  $subPoints
     */
    public function setSubPoints(array $subPoints): static
    {
        $this->subPoints = [];
        foreach ($subPoints as $point) {
            if ($point instanceof RelevantPoint) {
                $this->addSubPoint($point);
            }
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getGuidelines(): array
    {
        return $this->guidelines;
    }

    /**
     * @param  string[]  $guidelines
     */
    public function setGuidelines(array $guidelines): static
    {
        $this->guidelines = [];
        foreach ($guidelines as $line) {
            $this->addGuideline((string) $line);
        }

        return $this;
    }

    public function addGuideline(string $guideline): static
    {
        $guideline = trim($guideline);
        if ($guideline === '') {
            return $this;
        }

        $this->guidelines[] = $guideline;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'point' => $this->getPoint()->toArray(),
            'sub_points' => array_map(static fn (RelevantPoint $point) => $point->toArray(), $this->getSubPoints()),
            'guidelines' => $this->getGuidelines(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $item = new static;

        if (isset($data['point']) && is_array($data['point'])) {
            $item->setPoint(RelevantPoint::fromArray($data['point']));
        }

        if (isset($data['sub_points']) && is_array($data['sub_points'])) {
            $subPoints = [];
            foreach ($data['sub_points'] as $row) {
                if (is_array($row)) {
                    $subPoints[] = RelevantPoint::fromArray($row);
                }
            }
            $item->setSubPoints($subPoints);
        }

        if (isset($data['guidelines']) && is_array($data['guidelines'])) {
            $item->setGuidelines($data['guidelines']);
        }

        return $item;
    }
}
