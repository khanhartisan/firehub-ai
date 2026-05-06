<?php

namespace App\Contracts\IntentResolver;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;

final class Intent implements Serializable
{
    use SerializableTrait;

    protected ?string $title = null;

    protected ?string $description = null;

    protected ?Language $language = null;

    protected ?Temporal $temporal = null;

    /** @var list<IntentType> */
    protected array $types = [];

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLanguage(): Language
    {
        return $this->language;
    }

    public function setLanguage(Language $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getTemporal(): ?Temporal
    {
        return $this->temporal;
    }

    public function setTemporal(?Temporal $temporal): static
    {
        $this->temporal = $temporal;

        return $this;
    }

    /**
     * @return list<IntentType>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @param  list<IntentType>  $types
     *
     * @throws \InvalidArgumentException When an element is not an {@see IntentType} instance.
     */
    public function setTypes(array $types): static
    {
        foreach ($types as $index => $type) {
            if (! $type instanceof IntentType) {
                throw new \InvalidArgumentException(
                    sprintf('types[%s] must be an instance of %s, %s given.', $index, IntentType::class, get_debug_type($type))
                );
            }
        }

        $this->types = array_values($types);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return array{title: string|null, description: string|null, language: string|null, temporal: string|null, types: list<string>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'language' => $this->language?->value,
            'temporal' => $this->temporal?->value,
            'types' => array_map(
                static fn (IntentType $type): string => $type->value,
                $this->types,
            ),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $instance = new static;

        if (array_key_exists('title', $data)) {
            $title = $data['title'];
            $instance->setTitle($title === null ? null : (string) $title);
        }

        if (array_key_exists('description', $data)) {
            $description = $data['description'];
            $instance->setDescription($description === null ? null : (string) $description);
        }

        if (array_key_exists('language', $data)) {
            $language = $data['language'];
            if ($language instanceof Language) {
                $instance->setLanguage($language);
            } elseif ($language === null || $language === '') {
                throw new \InvalidArgumentException('Language is required');
            } elseif (is_string($language)) {
                $instance->setLanguage(Language::tryFrom($language));
            }
        }

        if (array_key_exists('temporal', $data)) {
            $temporal = $data['temporal'];
            if ($temporal === null || $temporal === '') {
                $instance->setTemporal(null);
            } elseif ($temporal instanceof Temporal) {
                $instance->setTemporal($temporal);
            } elseif (is_string($temporal)) {
                $instance->setTemporal(Temporal::tryFrom($temporal));
            }
        }

        if (isset($data['types']) && is_array($data['types'])) {
            $types = [];
            foreach ($data['types'] as $value) {
                if ($value instanceof IntentType) {
                    $types[] = $value;

                    continue;
                }
                if (is_string($value)) {
                    $type = IntentType::tryFrom($value);
                } else {
                    continue;
                }
                if ($type !== null) {
                    $types[] = $type;
                }
            }
            $instance->setTypes($types);
        }

        return $instance;
    }
}
