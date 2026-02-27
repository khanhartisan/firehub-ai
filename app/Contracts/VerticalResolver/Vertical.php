<?php

namespace App\Contracts\VerticalResolver;

use App\Concerns\Describable as DescribableConcern;
use App\Concerns\Identifiable as IdentifiableConcern;
use App\Concerns\Serializable as SerializableConcern;
use App\Contracts\Describable;
use App\Contracts\Identifiable;
use App\Contracts\Serializable;

final class Vertical implements Describable, Identifiable, Serializable
{
    use DescribableConcern;
    use IdentifiableConcern;
    use SerializableConcern;

    private string $name;

    /** @var Vertical[] */
    private array $children = [];

    public function __construct(string $name, ?string $description = null)
    {
        $this->setName($name);
        $this->setDescription($description);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function setChildren(array $children): static
    {
        $this->children = [];

        foreach ($children as $child) {
            $this->addChild($child);
        }

        return $this;
    }

    public function addChild(Vertical $vertical): static
    {
        $existingIdentifiers = $this->collectIdentifiersRecursive();
        $newIdentifiers = $vertical->collectIdentifiersRecursive();

        $duplicate = array_intersect($existingIdentifiers, $newIdentifiers);
        if ($duplicate !== []) {
            throw new \InvalidArgumentException(
                'Cannot add child: duplicate vertical identifier(s) in tree: ' . implode(', ', $duplicate)
            );
        }

        $this->children[] = $vertical;

        return $this;
    }

    /**
     * Collect all identifiers in this vertical and its descendants (identifier or name as fallback).
     *
     * @return array<int, string>
     */
    private function collectIdentifiersRecursive(): array
    {
        $id = $this->getIdentifier() ?? $this->getName();
        $ids = [$id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->collectIdentifiersRecursive());
        }

        return $ids;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function toArray(): array
    {
        return [
            'identifier' => $this->getIdentifier(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'children' => array_map(fn (Vertical $child) => $child->toArray(), $this->getChildren()),
        ];
    }

    public static function fromArray(array $data): static
    {
        $name = $data['name'] ?? throw new \InvalidArgumentException('Vertical data must contain "name"');
        $instance = new static($name, $data['description'] ?? null);

        if (isset($data['identifier'])) {
            $instance->setIdentifier($data['identifier']);
        }

        $children = $data['children'] ?? [];
        if ($children !== []) {
            $childVerticals = array_map(
                fn (array $childData) => static::fromArray($childData),
                $children
            );
            $instance->setChildren($childVerticals);
        }

        return $instance;
    }
}