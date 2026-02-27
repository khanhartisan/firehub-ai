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
    private array $children;

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
        // TODO: Recursively loop inside the current $this->>children
        // to confirm that there is no vertical with the same identifier found
        // otherwise throw an exception
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function toArray(): array
    {
        // TODO: Implement toArray() method.
    }

    public static function fromArray(array $data): static
    {
        // TODO: Implement fromArray() method.
    }
}