<?php

namespace App\Contracts\DOM;

use App\Concerns\AlwaysIdentifiable;
use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Identifiable;
use App\Contracts\Serializable;
use DOMDocument;
use DOMElement as NativeDomElement;
use DOMNode;
use Exception;

class Element implements Serializable, Identifiable
{
    use AlwaysIdentifiable;
    use SerializableTrait;

    protected int $maxIdentifierLength = 4;

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
        if ($child instanceof Element) {
            $this->removeByIdentifier($child->getIdentifier());
        }

        $this->children[] = $child;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function insertAfter(string $identifier, Element|string $child): static
    {
        return $this->insertRelativeToIdentifier($identifier, $child, true);
    }

    /**
     * @throws Exception
     */
    public function insertBefore(string $identifier, Element|string $child): static
    {
        return $this->insertRelativeToIdentifier($identifier, $child, false);
    }

    /**
     * @throws Exception
     */
    private function insertRelativeToIdentifier(string $identifier, Element|string $child, bool $insertAfter): static
    {
        if ($child instanceof Element) {
            $this->removeByIdentifier($child->getIdentifier());
        }

        if (! $this->tryInsertRelativeToIdentifier($identifier, $child, $insertAfter)) {
            throw new Exception('Child with the provided identifier was not found.');
        }

        return $this;
    }

    public function findByIdentifier(string $identifier): ?static
    {
        if ($this->getIdentifier() === $identifier) {
            return $this;
        }

        foreach ($this->children as $child) {
            if (! $child instanceof self) {
                continue;
            }

            $found = $child->findByIdentifier($identifier);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    public function removeChildByIdentifier(string $identifier): bool
    {
        foreach ($this->children as $index => $child) {
            if (! $child instanceof self) {
                continue;
            }

            if ($child->getIdentifier() === $identifier) {
                array_splice($this->children, $index, 1);

                return true;
            }
        }

        foreach ($this->children as $child) {
            if ($child instanceof self && $child->removeChildByIdentifier($identifier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Element>  $replacements
     */
    public function replaceByIdentifier(string $identifier, array $replacements): bool
    {
        if ($replacements === []) {
            return false;
        }

        foreach ($replacements as $replacement) {
            if ($replacement instanceof self && $replacement->getIdentifier() !== $identifier) {
                $this->removeByIdentifier($replacement->getIdentifier());
            }
        }

        foreach ($this->children as $index => $child) {
            if (! $child instanceof self) {
                continue;
            }

            if ($child->getIdentifier() === $identifier) {
                array_splice($this->children, $index, 1, $replacements);

                return true;
            }
        }

        foreach ($this->children as $child) {
            if ($child instanceof self && $child->replaceByIdentifier($identifier, $replacements)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Element|string>  $elements
     */
    public function insertAllBefore(string $identifier, array $elements): bool
    {
        if ($elements === []) {
            return false;
        }

        return $this->tryInsertAllRelativeToIdentifier($identifier, $elements, false);
    }

    /**
     * @param  list<Element|string>  $elements
     */
    public function insertAllAfter(string $identifier, array $elements): bool
    {
        if ($elements === []) {
            return false;
        }

        return $this->tryInsertAllRelativeToIdentifier($identifier, $elements, true);
    }

    public function removeByIdentifier(string $identifier): bool
    {
        $removed = false;

        for ($index = count($this->children) - 1; $index >= 0; $index--) {
            $existingChild = $this->children[$index];
            if (! $existingChild instanceof self) {
                continue;
            }

            if ($existingChild->getIdentifier() === $identifier) {
                array_splice($this->children, $index, 1);
                $removed = true;
            }
        }

        foreach ($this->children as $existingChild) {
            if ($existingChild instanceof self && $existingChild->removeByIdentifier($identifier)) {
                $removed = true;
            }
        }

        return $removed;
    }

    private function tryInsertRelativeToIdentifier(string $identifier, Element|string $child, bool $insertAfter): bool
    {
        foreach ($this->children as $index => $existingChild) {
            if (! $existingChild instanceof self) {
                continue;
            }

            if ($existingChild->getIdentifier() === $identifier) {
                $insertIndex = $insertAfter ? $index + 1 : $index;
                array_splice($this->children, $insertIndex, 0, [$child]);

                return true;
            }

            if ($existingChild->tryInsertRelativeToIdentifier($identifier, $child, $insertAfter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Element|string>  $elements
     */
    private function tryInsertAllRelativeToIdentifier(string $identifier, array $elements, bool $insertAfter): bool
    {
        foreach ($this->children as $index => $existingChild) {
            if (! $existingChild instanceof self) {
                continue;
            }

            if ($existingChild->getIdentifier() === $identifier) {
                foreach ($elements as $element) {
                    if ($element instanceof self) {
                        $this->removeByIdentifier($element->getIdentifier());
                    }
                }

                $insertIndex = $insertAfter ? $index + 1 : $index;
                array_splice($this->children, $insertIndex, 0, $elements);

                return true;
            }

            if ($existingChild->tryInsertAllRelativeToIdentifier($identifier, $elements, $insertAfter)) {
                return true;
            }
        }

        return false;
    }

    public function toHtml(bool $withIdentifier = false): string
    {
        $tag = $this->type?->value;
        if ($tag === null || $tag === '') {
            return $this->getInnerHtml($withIdentifier);
        }

        $attrs = $withIdentifier ? ' data-identifier="'.$this->getIdentifier().'"' : ' ';
        foreach ($this->props as $key => $value) {
            if (! is_string($key) || ! is_string($value) || $value === '') {
                continue;
            }

            $attrs .= ' '.$key.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        if (!trim($attrs)) {
            $attrs = '';
        }

        if (in_array($tag, ['img', 'br', 'hr'], true)) {
            return "<{$tag}{$attrs} />";
        }

        $childrenHtml = $this->getInnerHtml();

        return "<{$tag}{$attrs}>{$childrenHtml}</{$tag}>";
    }

    public static function fromHtml(string $html): static
    {
        $dom = new DOMDocument('1.0', 'UTF-8');

        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div data-parser-root="1">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $parserRoot = $dom->documentElement;
        $instance = new static;

        if (! $parserRoot instanceof NativeDomElement) {
            return $instance;
        }

        $rootNodes = [];
        foreach ($parserRoot->childNodes as $childNode) {
            $rootNodes[] = $childNode;
        }

        if (count($rootNodes) === 1 && $rootNodes[0] instanceof NativeDomElement) {
            $instance->hydrateFromDomElement($rootNodes[0], true);

            return $instance;
        }

        $instance->setChildren(self::parseDomChildren($rootNodes));

        return $instance;
    }

    /**
     * @param  array<int, DOMNode>  $nodes
     * @return array<int, Element|string>
     */
    private static function parseDomChildren(array $nodes): array
    {
        $children = [];

        foreach ($nodes as $node) {
            if ($node instanceof NativeDomElement) {
                $children[] = self::fromDomElement($node);
                continue;
            }

            if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
                $children[] = $node->nodeValue ?? '';
            }
        }

        return $children;
    }

    private static function fromDomElement(NativeDomElement $domElement): self
    {
        $element = new self;
        $element->hydrateFromDomElement($domElement, false);

        return $element;
    }

    private function hydrateFromDomElement(NativeDomElement $domElement, bool $isRoot): void
    {
        $tag = strtolower($domElement->tagName);
        $type = ElementType::tryFrom($tag);
        if ($type !== null) {
            try {
                $this->setType($type);
            } catch (Exception) {
                // Some subclasses disallow overriding type.
            }
        }

        if ($domElement->hasAttributes()) {
            foreach ($domElement->attributes as $attribute) {
                if (! isset($attribute->name, $attribute->value)) {
                    continue;
                }

                if ($attribute->name === 'data-identifier') {
                    $this->setIdentifier($attribute->value);
                    continue;
                }

                $this->setProp($attribute->name, $attribute->value);
            }
        }

        $nodes = [];
        foreach ($domElement->childNodes as $childNode) {
            $nodes[] = $childNode;
        }

        if (! $isRoot || $nodes !== []) {
            $this->setChildren(self::parseDomChildren($nodes));
        }
    }

    public function getInnerHtml(bool $withIdentifier = false): string
    {
        return implode('', array_map(function (Element|string $child) use ($withIdentifier): string {
            if ($child instanceof self) {
                return $child->toHtml($withIdentifier);
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
            'identifier' => $this->getIdentifier(),
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

        if (isset($data['identifier'])) {
            $element->setIdentifier($data['identifier']);
        }

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