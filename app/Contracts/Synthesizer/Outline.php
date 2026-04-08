<?php

namespace App\Contracts\Synthesizer;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

/**
 * Top-level synthesizer outline: optional title and ordered outline items.
 */
final class Outline implements Serializable
{
    use SerializableTrait;

    protected ?string $title = null;

    /** @var OutlineItem[] */
    protected array $items = [];

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return OutlineItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function addItem(OutlineItem $item): static
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * @param  OutlineItem[]  $items
     */
    public function setItems(array $items): static
    {
        $this->items = [];
        foreach ($items as $item) {
            $this->addItem($item);
        }

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'items' => array_map(static fn (OutlineItem $item) => $item->toArray(), $this->items),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $outline = new static;

        if (isset($data['title'])) {
            $outline->setTitle($data['title'] !== null ? (string) $data['title'] : null);
        }

        if (isset($data['items']) && is_array($data['items'])) {
            $items = [];
            foreach ($data['items'] as $row) {
                if (is_array($row)) {
                    $items[] = OutlineItem::fromArray($row);
                }
            }
            $outline->setItems($items);
        }

        return $outline;
    }
}
