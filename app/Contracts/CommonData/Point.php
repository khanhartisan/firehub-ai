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

    protected ?Verification $verification = null;

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

    public function getVerification(): ?Verification
    {
        return $this->verification;
    }

    public function setVerification(?Verification $verification): static
    {
        $this->verification = $verification;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'headline' => $this->getHeadline(),
            'description' => $this->getDescription(),
            'evidences' => $this->getEvidences(),
            'verification' => $this->getVerification()?->toArray(),
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

        if (array_key_exists('verification', $data)) {
            if ($data['verification'] instanceof Verification) {
                $point->setVerification($data['verification']);
            } elseif (is_array($data['verification'])) {
                $point->setVerification(Verification::fromArray($data['verification']));
            } elseif ($data['verification'] === null) {
                $point->setVerification(null);
            }
        }

        return $point;
    }
}