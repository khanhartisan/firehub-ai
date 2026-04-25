<?php

namespace App\Contracts\CommonData;

use App\Contracts\CommonData\Concerns\HasMeta;
use App\Contracts\CommonData\Concerns\HasVerification;
use App\Contracts\FactChecker\FactCheckable;
use App\Contracts\Serializable;

class Point implements FactCheckable, Serializable
{
    use HasVerification;
    use HasMeta;
    use \App\Concerns\Serializable;

    /**
     * @var ?string A concise, punchy summary of the insight.
     */
    protected ?string $headline = null;

    /**
     * @var ?string Detailed explanation or supporting logic for the headline.
     */
    protected ?string $description = null;

    /**
     * @var string[] List of raw facts, statistics, or quotes supporting this point.
     */
    protected array $evidences = [];

    public function getFactClaim(): string
    {
        $factClaim = '';

        if ($headline = $this->getHeadline()) {
            $factClaim .= $headline."\n";
        }

        if ($description = $this->getDescription()) {
            $factClaim .= $description."\n";
        }

        if ($evidences = $this->getEvidences()) {
            $factClaim .= "\nEvidences:\n";
            foreach ($evidences as $evidence) {
                $factClaim .= '- '.$evidence."\n";
            }
        }

        return $factClaim;
    }

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    public function setHeadline(?string $headline): static
    {
        $this->headline = $headline;

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

    /**
     * @return string[]
     */
    public function getEvidences(): array
    {
        return $this->evidences;
    }

    /**
     * @param  string[]  $evidences
     */
    public function setEvidences(array $evidences): static
    {
        $this->evidences = array_values(array_map(static fn ($evidence) => (string) $evidence, $evidences));

        return $this;
    }

    public function toArray(): array
    {
        return [
            'headline' => $this->getHeadline(),
            'description' => $this->getDescription(),
            'evidences' => $this->getEvidences(),
            'verification' => $this->getVerification()?->toArray(),
            'meta' => $this->getMeta(),
        ];
    }

    public static function fromArray(array $data): static
    {
        $point = new static;

        if (array_key_exists('headline', $data)) {
            $point->setHeadline($data['headline'] !== null ? (string) $data['headline'] : null);
        }

        if (array_key_exists('description', $data)) {
            $point->setDescription($data['description'] !== null ? (string) $data['description'] : null);
        }

        if (isset($data['evidences']) && is_array($data['evidences'])) {
            $point->setEvidences($data['evidences']);
        }

        return $point
            ->hydrateMeta($data)
            ->hydrateVerification($data);
    }
}