<?php

namespace App\Contracts\Synthesizer\Author;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use InvalidArgumentException;

/**
 * Maps an {@see \App\Contracts\Synthesizer\Illustration\IllustrationResult} (by identifier) to a
 * {@see \App\Contracts\DOM\Element} insertion point in the article DOM.
 */
final class IllustrationAnchor implements Serializable
{
    use SerializableTrait;

    protected string $illustrationIdentifier;

    protected string $elementIdentifier;

    /**
     * When true, the illustration is placed after the element; when false, before it.
     */
    protected bool $isAfter = true;

    public function __construct(
        string $illustrationIdentifier,
        string $elementIdentifier,
        bool $isAfter = true,
    ) {
        $this->setIllustrationIdentifier($illustrationIdentifier);
        $this->setElementIdentifier($elementIdentifier);
        $this->setIsAfter($isAfter);
    }

    public function getIllustrationIdentifier(): string
    {
        return $this->illustrationIdentifier;
    }

    public function setIllustrationIdentifier(string $illustrationIdentifier): static
    {
        $value = trim($illustrationIdentifier);
        if ($value === '') {
            throw new InvalidArgumentException('Illustration identifier cannot be empty.');
        }

        $this->illustrationIdentifier = $value;

        return $this;
    }

    public function getElementIdentifier(): string
    {
        return $this->elementIdentifier;
    }

    public function setElementIdentifier(string $elementIdentifier): static
    {
        $value = trim($elementIdentifier);
        if ($value === '') {
            throw new InvalidArgumentException('Element identifier cannot be empty.');
        }

        $this->elementIdentifier = $value;

        return $this;
    }

    public function isAfter(): bool
    {
        return $this->isAfter;
    }

    public function setIsAfter(bool $isAfter): static
    {
        $this->isAfter = $isAfter;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'illustration_identifier' => $this->getIllustrationIdentifier(),
            'element_identifier' => $this->getElementIdentifier(),
            'is_after' => $this->isAfter(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        if (! isset($data['illustration_identifier']) || ! is_string($data['illustration_identifier'])) {
            throw new InvalidArgumentException('illustration_identifier must be a non-empty string.');
        }

        if (! isset($data['element_identifier']) || ! is_string($data['element_identifier'])) {
            throw new InvalidArgumentException('element_identifier must be a non-empty string.');
        }

        $illustrationId = trim($data['illustration_identifier']);
        $elementId = trim($data['element_identifier']);

        if ($illustrationId === '' || $elementId === '') {
            throw new InvalidArgumentException('illustration_identifier and element_identifier cannot be empty.');
        }

        $isAfter = true;
        if (array_key_exists('is_after', $data)) {
            $isAfter = (bool) $data['is_after'];
        }

        return new static($illustrationId, $elementId, $isAfter);
    }
}
