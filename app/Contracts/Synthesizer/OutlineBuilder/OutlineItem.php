<?php

namespace App\Contracts\Synthesizer\OutlineBuilder;

use App\Concerns\AlwaysIdentifiable;
use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Identifiable;
use App\Contracts\Serializable;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;

/**
 * One node in a synthesizer outline backed by a primary point and optional nested sub-items.
 */
final class OutlineItem implements Identifiable, Serializable
{
    use AlwaysIdentifiable;
    use SerializableTrait;

    protected int $defaultIdentifierLength = 4;

    protected RelevantPoint $point;

    /** @var OutlineItem[] */
    protected array $subItems = [];

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
     * @return OutlineItem[]
     */
    public function getSubItems(): array
    {
        return $this->subItems;
    }

    public function addSubItem(OutlineItem $item): static
    {
        $this->subItems[] = $item;

        return $this;
    }

    /**
     * @param  OutlineItem[]  $subItems
     */
    public function setSubItems(array $subItems): static
    {
        $this->subItems = [];
        foreach ($subItems as $item) {
            if ($item instanceof OutlineItem) {
                $this->addSubItem($item);
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
            'identifier' => $this->getIdentifier(),
            'point' => $this->getPoint()->toArray(),
            'sub_items' => array_map(static fn (OutlineItem $item) => $item->toArray(), $this->getSubItems()),
            'guidelines' => $this->getGuidelines(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $item = new static;

        if (isset($data['identifier']) and is_string($data['identifier'])) {
            $item->setIdentifier($data['identifier']);
        }

        if (isset($data['point']) && is_array($data['point'])) {
            $item->setPoint(RelevantPoint::fromArray($data['point']));
        }

        if (isset($data['sub_items']) && is_array($data['sub_items'])) {
            $subItems = [];
            foreach ($data['sub_items'] as $row) {
                if (is_array($row)) {
                    $subItems[] = OutlineItem::fromArray($row);
                }
            }
            $item->setSubItems($subItems);
        }

        if (isset($data['guidelines']) && is_array($data['guidelines'])) {
            $item->setGuidelines($data['guidelines']);
        }

        return $item;
    }
}
