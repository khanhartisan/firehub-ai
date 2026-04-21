<?php

namespace App\Contracts\CommonData;

use App\Contracts\Serializable;
use App\Enums\Country;
use App\Enums\KnowledgeLevel;
use App\Enums\Language;

final class Audience implements Serializable
{
    use \App\Concerns\Serializable;

    /**
     * A relative priority weight number,
     * helpful if multiple audiences present at the same time
     *
     * @var float|null Ranging from 0.00 to 1.00
     */
    protected ?float $priorityWeight = null;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?int $ageFrom = null;

    protected ?int $ageTo = null;

    protected ?KnowledgeLevel $knowledgeLevel = null;

    protected ?Language $language = null;

    /** @var Country[] */
    protected array $countries = [];

    /**
     * Current frustrations or challenges the audience is facing.
     * Example of a pain point:
     *
     * @example ["High operational costs", "Lack of specialized personnel"]
     * @var string[]
     */
    protected array $painPoints = [];

    /**
     * Future-looking anxieties, barriers, or questions when considering a solution.
     * Used for proactive objection handling in the content.
     *
     * @example ["Data privacy concerns", "Complexity of integration", "Hidden fees"]
     * @var array
     */
    protected array $concerns = [];

    /**
     * Long-term goals, dreams, or the 'ideal state' the audience desires.
     *
     * @example ["Becoming a data-driven leader", "Halving the operational workload"]
     * @var array
     */
    protected array $aspirations = [];

    /**
     * Deep-seated emotional or professional threats the audience wants to avoid.
     *
     * @example ["Being replaced by automation", "Losing market share to competitors"]
     * @var array
     */
    protected array $fears = [];

    public function getPriorityWeight(): ?float
    {
        return $this->priorityWeight;
    }

    public function setPriorityWeight(?float $priorityWeight): static
    {
        if ($priorityWeight !== null && ($priorityWeight < 0 || $priorityWeight > 1)) {
            throw new \InvalidArgumentException('priorityWeight must be between 0 and 1.');
        }

        $this->priorityWeight = $priorityWeight;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAgeFrom(): ?int
    {
        return $this->ageFrom;
    }

    public function setAgeFrom(?int $ageFrom): static
    {
        $this->ageFrom = $ageFrom;

        return $this;
    }

    public function getAgeTo(): ?int
    {
        return $this->ageTo;
    }

    public function setAgeTo(?int $ageTo): static
    {
        $this->ageTo = $ageTo;

        return $this;
    }

    public function getKnowledgeLevel(): ?KnowledgeLevel
    {
        return $this->knowledgeLevel;
    }

    public function setKnowledgeLevel(?KnowledgeLevel $knowledgeLevel): static
    {
        $this->knowledgeLevel = $knowledgeLevel;

        return $this;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(?Language $language): static
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @return Country[]
     */
    public function getCountries(): array
    {
        return $this->countries;
    }

    /**
     * @param  Country[]  $countries
     */
    public function setCountries(array $countries): static
    {
        $this->countries = [];
        foreach ($countries as $index => $country) {
            if (! $country instanceof Country) {
                throw new \InvalidArgumentException(
                    sprintf('countries[%s] must be an instance of %s, %s given.', $index, Country::class, get_debug_type($country))
                );
            }

            $this->countries[] = $country;
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getPainPoints(): array
    {
        return $this->painPoints;
    }

    /**
     * @param  string[]  $painPoints
     */
    public function setPainPoints(array $painPoints): static
    {
        $this->painPoints = array_values(array_map(static fn ($v): string => (string) $v, $painPoints));

        return $this;
    }

    /**
     * @return string[]
     */
    public function getConcerns(): array
    {
        return $this->concerns;
    }

    /**
     * @param  string[]  $concerns
     */
    public function setConcerns(array $concerns): static
    {
        $this->concerns = array_values(array_map(static fn ($v): string => (string) $v, $concerns));

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAspirations(): array
    {
        return $this->aspirations;
    }

    /**
     * @param  string[]  $aspirations
     */
    public function setAspirations(array $aspirations): static
    {
        $this->aspirations = array_values(array_map(static fn ($v): string => (string) $v, $aspirations));

        return $this;
    }

    /**
     * @return string[]
     */
    public function getFears(): array
    {
        return $this->fears;
    }

    /**
     * @param  string[]  $fears
     */
    public function setFears(array $fears): static
    {
        $this->fears = array_values(array_map(static fn ($v): string => (string) $v, $fears));

        return $this;
    }

    public function toArray(): array
    {
        return [
            'priority_weight' => $this->getPriorityWeight(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'age_from' => $this->getAgeFrom(),
            'age_to' => $this->getAgeTo(),
            'knowledge_level' => $this->getKnowledgeLevel()?->value,
            'language' => $this->getLanguage()?->value,
            'countries' => array_map(static fn (Country $country): string => $country->value, $this->getCountries()),
            'pain_points' => $this->getPainPoints(),
            'concerns' => $this->getConcerns(),
            'aspirations' => $this->getAspirations(),
            'fears' => $this->getFears(),
        ];
    }

    public static function fromArray(array $data): static
    {
        $audience = new static;

        if (array_key_exists('priority_weight', $data)) {
            $audience->setPriorityWeight($data['priority_weight'] !== null ? (float) $data['priority_weight'] : null);
        }

        if (array_key_exists('name', $data)) {
            $audience->setName($data['name'] !== null ? (string) $data['name'] : null);
        }

        if (array_key_exists('description', $data)) {
            $audience->setDescription($data['description'] !== null ? (string) $data['description'] : null);
        }

        if (array_key_exists('age_from', $data)) {
            $audience->setAgeFrom($data['age_from'] !== null ? (int) $data['age_from'] : null);
        }

        if (array_key_exists('age_to', $data)) {
            $audience->setAgeTo($data['age_to'] !== null ? (int) $data['age_to'] : null);
        }

        if (array_key_exists('knowledge_level', $data)) {
            $raw = $data['knowledge_level'];
            if ($raw === null || $raw === '') {
                $audience->setKnowledgeLevel(null);
            } else {
                $audience->setKnowledgeLevel($raw instanceof KnowledgeLevel ? $raw : KnowledgeLevel::tryFrom($raw));
            }
        }

        if (array_key_exists('language', $data)) {
            $raw = $data['language'];
            if ($raw === null || $raw === '') {
                $audience->setLanguage(null);
            } else {
                $audience->setLanguage($raw instanceof Language ? $raw : Language::tryFrom((string) $raw));
            }
        }

        if (isset($data['countries']) && is_array($data['countries'])) {
            $countries = [];
            foreach ($data['countries'] as $country) {
                if ($country instanceof Country) {
                    $countries[] = $country;

                    continue;
                }

                if (! is_scalar($country) && $country !== null) {
                    continue;
                }

                $resolved = Country::tryFrom(strtoupper((string) $country));
                if ($resolved instanceof Country) {
                    $countries[] = $resolved;
                }
            }
            $audience->setCountries($countries);
        }

        if (isset($data['pain_points']) && is_array($data['pain_points'])) {
            $audience->setPainPoints($data['pain_points']);
        }

        if (isset($data['concerns']) && is_array($data['concerns'])) {
            $audience->setConcerns($data['concerns']);
        }

        if (isset($data['aspirations']) && is_array($data['aspirations'])) {
            $audience->setAspirations($data['aspirations']);
        }

        if (isset($data['fears']) && is_array($data['fears'])) {
            $audience->setFears($data['fears']);
        }

        return $audience;
    }
}