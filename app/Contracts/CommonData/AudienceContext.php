<?php

namespace App\Contracts\CommonData;

use App\Enums\Country;
use App\Enums\KnowledgeLevel;
use App\Enums\Language;

class AudienceContext extends SemanticContext
{
    public function setPriorityWeight(?float $priorityWeight, ?float $weight = null): static
    {
        if ($priorityWeight !== null && ($priorityWeight < 0 || $priorityWeight > 1)) {
            throw new \InvalidArgumentException('priorityWeight must be between 0 and 1.');
        }

        return $this->set('priority_weight', 'Audience priority weight between 0 and 1.', $priorityWeight !== null ? round($priorityWeight, 2) : null, $weight);
    }

    public function setName(?string $name, ?float $weight = null): static
    {
        return $this->set('name', 'Audience name.', $name, $weight);
    }

    public function setDescription(?string $description, ?float $weight = null): static
    {
        return $this->set('description', 'Audience description.', $description, $weight);
    }

    public function setAgeFrom(?int $ageFrom, ?float $weight = null): static
    {
        return $this->set('age_from', 'Minimum audience age.', $ageFrom, $weight);
    }

    public function setAgeTo(?int $ageTo, ?float $weight = null): static
    {
        return $this->set('age_to', 'Maximum audience age.', $ageTo, $weight);
    }

    public function setKnowledgeLevel(?KnowledgeLevel $knowledgeLevel, ?float $weight = null): static
    {
        return $this->set('knowledge_level', 'Audience knowledge level enum value.', $knowledgeLevel?->value, $weight);
    }

    public function setLanguage(?Language $language, ?float $weight = null): static
    {
        return $this->set('language', 'Audience language code.', $language?->value, $weight);
    }

    /**
     * @param Country[] $countries
     */
    public function setCountries(array $countries, ?float $weight = null): static
    {
        $values = [];
        foreach ($countries as $index => $country) {
            if (! $country instanceof Country) {
                throw new \InvalidArgumentException(sprintf('countries[%s] must be an instance of %s, %s given.', $index, Country::class, get_debug_type($country)));
            }
            $values[] = $country->value;
        }

        return $this->set('countries', 'Audience countries (ISO codes).', $values, $weight);
    }

    public function setPainPoints(array $painPoints, ?float $weight = null): static
    {
        return $this->set('pain_points', 'Audience pain points.', array_values(array_map(static fn ($v): string => (string) $v, $painPoints)), $weight);
    }

    public function setConcerns(array $concerns, ?float $weight = null): static
    {
        return $this->set('concerns', 'Audience concerns.', array_values(array_map(static fn ($v): string => (string) $v, $concerns)), $weight);
    }

    public function setAspirations(array $aspirations, ?float $weight = null): static
    {
        return $this->set('aspirations', 'Audience aspirations.', array_values(array_map(static fn ($v): string => (string) $v, $aspirations)), $weight);
    }

    public function setFears(array $fears, ?float $weight = null): static
    {
        return $this->set('fears', 'Audience fears.', array_values(array_map(static fn ($v): string => (string) $v, $fears)), $weight);
    }
}
