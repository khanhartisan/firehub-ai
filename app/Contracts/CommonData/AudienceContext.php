<?php

namespace App\Contracts\CommonData;

use App\Enums\Country;
use App\Enums\KnowledgeLevel;
use App\Enums\Language;

class AudienceContext extends SemanticContext
{
    public function setPriorityWeight(?float $priorityWeight): static
    {
        if ($priorityWeight !== null && ($priorityWeight < 0 || $priorityWeight > 1)) {
            throw new \InvalidArgumentException('priorityWeight must be between 0 and 1.');
        }

        return $this->set('priority_weight', 'Audience priority weight between 0 and 1.', $priorityWeight !== null ? round($priorityWeight, 2) : null);
    }

    public function setName(?string $name): static
    {
        return $this->set('name', 'Audience name.', $name);
    }

    public function setDescription(?string $description): static
    {
        return $this->set('description', 'Audience description.', $description);
    }

    public function setAgeFrom(?int $ageFrom): static
    {
        return $this->set('age_from', 'Minimum audience age.', $ageFrom);
    }

    public function setAgeTo(?int $ageTo): static
    {
        return $this->set('age_to', 'Maximum audience age.', $ageTo);
    }

    public function setKnowledgeLevel(?KnowledgeLevel $knowledgeLevel): static
    {
        return $this->set('knowledge_level', 'Audience knowledge level enum value.', $knowledgeLevel?->value);
    }

    public function setLanguage(?Language $language): static
    {
        return $this->set('language', 'Audience language code.', $language?->value);
    }

    /**
     * @param Country[] $countries
     */
    public function setCountries(array $countries): static
    {
        $values = [];
        foreach ($countries as $index => $country) {
            if (! $country instanceof Country) {
                throw new \InvalidArgumentException(sprintf('countries[%s] must be an instance of %s, %s given.', $index, Country::class, get_debug_type($country)));
            }
            $values[] = $country->value;
        }

        return $this->set('countries', 'Audience countries (ISO codes).', $values);
    }

    public function setPainPoints(array $painPoints): static
    {
        return $this->set('pain_points', 'Audience pain points.', array_values(array_map(static fn ($v): string => (string) $v, $painPoints)));
    }

    public function setConcerns(array $concerns): static
    {
        return $this->set('concerns', 'Audience concerns.', array_values(array_map(static fn ($v): string => (string) $v, $concerns)));
    }

    public function setAspirations(array $aspirations): static
    {
        return $this->set('aspirations', 'Audience aspirations.', array_values(array_map(static fn ($v): string => (string) $v, $aspirations)));
    }

    public function setFears(array $fears): static
    {
        return $this->set('fears', 'Audience fears.', array_values(array_map(static fn ($v): string => (string) $v, $fears)));
    }
}
