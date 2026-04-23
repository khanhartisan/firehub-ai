<?php

namespace App\Contracts\CommonData;

use App\Contracts\Serializable;

final class Point implements Serializable
{
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

    /**
     * A score from 0.00 to 1.00 representing
     * the semantic reliability of the data.
     *
     * @var float|null
     */
    protected ?float $confidence = null;

    /**
     * A definitive flag indicating whether
     * this point has passed the final quality
     *
     * @var bool
     */
    protected bool $isVerified = false;

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

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function setConfidence(?float $confidence): static
    {
        $this->confidence = $confidence;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'headline' => $this->getHeadline(),
            'description' => $this->getDescription(),
            'evidences' => $this->getEvidences(),
            'confidence' => $this->getConfidence(),
            'is_verified' => $this->isVerified(),
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

        if (array_key_exists('confidence', $data)) {
            $point->setConfidence($data['confidence'] !== null ? (float) $data['confidence'] : null);
        }

        if (array_key_exists('is_verified', $data)) {
            $point->setIsVerified((bool) $data['is_verified']);
        }

        return $point;
    }
}