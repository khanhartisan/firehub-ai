<?php

namespace App\Contracts\Synthesizer;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

/**
 * One node in a synthesizer outline: heading, optional brief, instructions, and nested items.
 */
final class OutlineItem implements Serializable
{
    use SerializableTrait;

    protected string $heading = '';

    protected ?string $brief = null;

    /**
     * @var string[]
     */
    protected array $instructions = [];

    /**
     * @var static[]
     */
    protected array $subItems = [];

    public function getHeading(): string
    {
        return $this->heading;
    }

    public function setHeading(string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function getBrief(): ?string
    {
        return $this->brief;
    }

    public function setBrief(?string $brief): static
    {
        $this->brief = $brief;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getInstructions(): array
    {
        return $this->instructions;
    }

    /**
     * @param  string[]  $instructions
     */
    public function setInstructions(array $instructions): static
    {
        $this->instructions = array_values(array_map(static fn ($line) => (string) $line, $instructions));

        return $this;
    }

    /**
     * @return static[]
     */
    public function getSubItems(): array
    {
        return $this->subItems;
    }

    public function addItem(self $item): static
    {
        $this->subItems[] = $item;

        return $this;
    }

    /**
     * @param  static[]  $subItems
     */
    public function setSubItems(array $subItems): static
    {
        $this->subItems = [];
        foreach ($subItems as $item) {
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
            'heading' => $this->heading,
            'brief' => $this->brief,
            'instructions' => $this->instructions,
            'sub_items' => array_map(static fn (self $item) => $item->toArray(), $this->subItems),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $item = new static;

        if (isset($data['heading'])) {
            $item->setHeading((string) $data['heading']);
        }

        if (isset($data['brief'])) {
            $item->setBrief($data['brief'] !== null ? (string) $data['brief'] : null);
        }

        if (isset($data['instructions']) && is_array($data['instructions'])) {
            $item->setInstructions($data['instructions']);
        }

        if (isset($data['sub_items']) && is_array($data['sub_items'])) {
            $subItems = [];
            foreach ($data['sub_items'] as $row) {
                if (is_array($row)) {
                    $subItems[] = static::fromArray($row);
                }
            }
            $item->setSubItems($subItems);
        }

        return $item;
    }
}
