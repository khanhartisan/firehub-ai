<?php

namespace App\Contracts\VerticalResolver;

use App\Concerns\Describable as DescribableConcern;
use App\Concerns\Identifiable as IdentifiableConcern;
use App\Concerns\Serializable as SerializableConcern;
use App\Contracts\Describable;
use App\Contracts\Identifiable;
use App\Contracts\Serializable;

/**
 * A business vertical (category) used by the VerticalResolver.
 *
 * Can form a tree via children. Identifiers must be unique within the tree.
 * Serializable for storage and API payloads.
 */
final class Vertical implements Describable, Identifiable, Serializable
{
    use DescribableConcern;
    use IdentifiableConcern;
    use SerializableConcern;

    /** Display name of the vertical. */
    private string $name;

    /** @var Vertical[] Child verticals (sub-categories). */
    private array $children = [];

    /**
     * @param  string  $name  Display name of the vertical.
     * @param  string|null  $description  Optional description.
     */
    public function __construct(string $name, ?string $description = null)
    {
        $this->setName($name);
        $this->setDescription($description);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return static */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Replace all children with the given list.
     * Duplicate identifiers in the tree will cause an exception.
     *
     * @param  Vertical[]  $children
     * @return static
     */
    public function setChildren(array $children): static
    {
        $this->children = [];

        foreach ($children as $child) {
            $this->addChild($child);
        }

        return $this;
    }

    /**
     * Add a child vertical. Throws if the child (or any of its descendants)
     * has an identifier that already exists in this tree.
     *
     * @param  Vertical  $vertical  Child to add.
     * @return static
     * @throws \InvalidArgumentException  When a duplicate identifier is found in the tree.
     */
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
     * Collect all identifiers in this vertical and its descendants.
     * Uses getIdentifier() when set, otherwise getName().
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

    /**
     * @return Vertical[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * {@inheritdoc}
     *
     * @return array{identifier: string|null, name: string, description: string|null, children: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->getIdentifier(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'children' => array_map(fn (Vertical $child) => $child->toArray(), $this->getChildren()),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param  array{name: string, description?: string|null, identifier?: string|null, children?: array<int, array<string, mixed>>}  $data
     * @return static
     * @throws \InvalidArgumentException  When "name" is missing.
     */
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