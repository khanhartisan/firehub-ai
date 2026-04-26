<?php

namespace App\Contracts\DOM;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

class Element implements Serializable
{
    use SerializableTrait;

    protected ?ElementType $type = null;

    /**
     * @var array<string, mixed>
     */
    protected array $props = [];

    /**
     * @var array<int, Element|string>
     */
    protected array $children = [];

    public function getType(): ?ElementType
    {
        return $this->type;
    }

    public function setType(?ElementType $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProps(): array
    {
        return $this->props;
    }

    /**
     * @param  array<string, string>  $props
     */
    public function setProps(array $props): static
    {
        $this->props = [];
        foreach ($props as $key => $value) {
            if (! is_string($key) or !is_string($value)) {
                continue;
            }

            $this->setProp($key, $value);
        }

        return $this;
    }

    public function setProp(string $key, string $value): static
    {
        $this->props[$key] = $value;

        return $this;
    }

    /**
     * @return array<int, Element|string>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param  array<int, Element|string>  $children
     */
    public function setChildren(array $children): static
    {
        $this->children = [];
        foreach ($children as $child) {
            if ($child instanceof self || is_string($child)) {
                $this->children[] = $child;
            }
        }

        return $this;
    }

    public function addChild(Element|string $child): static
    {
        $this->children[] = $child;

        return $this;
    }

    public function toHtml(): string
    {
        $tag = $this->type?->value;
        if ($tag === null || $tag === '') {
            return $this->getInnerHtml();
        }

        $attrs = '';
        foreach ($this->props as $key => $value) {
            if (! is_string($key) || ! is_string($value) || $value === '') {
                continue;
            }

            $attrs .= ' '.$key.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        if (in_array($tag, ['img', 'br', 'hr'], true)) {
            return "<{$tag}{$attrs} />";
        }

        $childrenHtml = $this->getInnerHtml();

        return "<{$tag}{$attrs}>{$childrenHtml}</{$tag}>";
    }

    public function getInnerHtml(): string
    {
        return implode('', array_map(function (Element|string $child): string {
            if ($child instanceof self) {
                return $child->toHtml();
            }

            return htmlspecialchars($child, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $this->children));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type?->value,
            'props' => $this->props,
            'children' => array_map(
                static fn (Element|string $child): array|string => $child instanceof self ? $child->toArray() : $child,
                $this->children
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $element = new static;

        if (isset($data['type']) && is_string($data['type'])) {
            $element->setType(ElementType::tryFrom($data['type']));
        }

        if (isset($data['props']) && is_array($data['props'])) {
            $element->setProps($data['props']);
        }

        if (isset($data['children']) && is_array($data['children'])) {
            $children = [];

            foreach ($data['children'] as $child) {
                if (is_array($child)) {
                    $children[] = static::fromArray($child);
                    continue;
                }

                if (is_string($child)) {
                    $children[] = $child;
                }
            }

            $element->setChildren($children);
        }

        return $element;
    }
}