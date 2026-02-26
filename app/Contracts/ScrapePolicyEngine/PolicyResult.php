<?php

namespace App\Contracts\ScrapePolicyEngine;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use Carbon\Carbon;

/**
 * Result of ScrapePolicyEngine::evaluate(): when to scrape next and policy metrics.
 *
 * next_scrape_at is stored on the entity; boosts/penalties/priority/urgency/cost_factor
 * are stored in entity.policy_result for analytics or future policy logic.
 */
final class PolicyResult implements Serializable
{
    use SerializableTrait;

    /** When the entity should be scraped next (used by scheduler). */
    private ?Carbon $nextScrapeAt = null;

    /** 0–1: boost from content change rate. */
    private float $changeBoost = 0.0;

    /** 0–1: boost from content value. */
    private float $valueBoost = 0.0;

    /** 0–1: penalty from recent errors. */
    private float $errorPenalty = 0.0;

    /** 0–1: source/entity priority. */
    private float $priority = 0.0;

    /** 0–1: how urgent a re-scrape is. */
    private float $urgency = 0.0;

    /** 0–1: cost factor for budgeting. */
    private float $costFactor = 0.0;

    public function getNextScrapeAt(): ?Carbon
    {
        return $this->nextScrapeAt;
    }

    public function setNextScrapeAt(?Carbon $nextScrapeAt): static
    {
        $this->nextScrapeAt = $nextScrapeAt;
        return $this;
    }

    public function getChangeBoost(): float
    {
        return $this->changeBoost;
    }

    public function setChangeBoost(float $changeBoost): static
    {
        $this->changeBoost = round(max(0.0, min(1.0, $changeBoost)), 2);
        return $this;
    }

    public function getValueBoost(): float
    {
        return $this->valueBoost;
    }

    public function setValueBoost(float $valueBoost): static
    {
        $this->valueBoost = round(max(0.0, min(1.0, $valueBoost)), 2);
        return $this;
    }

    public function getErrorPenalty(): float
    {
        return $this->errorPenalty;
    }

    public function setErrorPenalty(float $errorPenalty): static
    {
        $this->errorPenalty = round(max(0.0, min(1.0, $errorPenalty)), 2);
        return $this;
    }

    public function getPriority(): float
    {
        return $this->priority;
    }

    public function setPriority(float $priority): static
    {
        $this->priority = round(max(0.0, min(1.0, $priority)), 2);
        return $this;
    }

    public function getUrgency(): float
    {
        return $this->urgency;
    }

    public function setUrgency(float $urgency): static
    {
        $this->urgency = round(max(0.0, min(1.0, $urgency)), 2);
        return $this;
    }

    public function getCostFactor(): float
    {
        return $this->costFactor;
    }

    public function setCostFactor(float $costFactor): static
    {
        $this->costFactor = round(max(0.0, min(1.0, $costFactor)), 2);
        return $this;
    }

    /**
     * Convert the policy result to an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'next_scrape_at' => $this->nextScrapeAt?->toIso8601String(),
            'change_boost' => $this->changeBoost,
            'value_boost' => $this->valueBoost,
            'error_penalty' => $this->errorPenalty,
            'priority' => $this->priority,
            'urgency' => $this->urgency,
            'cost_factor' => $this->costFactor,
        ];
    }

    /**
     * Create an instance from an array representation.
     *
     * @param  array<string, mixed>  $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $result = new static();

        if (isset($data['next_scrape_at'])) {
            $result->setNextScrapeAt(Carbon::parse($data['next_scrape_at']));
        }

        if (isset($data['change_boost'])) {
            $result->setChangeBoost((float) $data['change_boost']);
        }

        if (isset($data['value_boost'])) {
            $result->setValueBoost((float) $data['value_boost']);
        }

        if (isset($data['error_penalty'])) {
            $result->setErrorPenalty((float) $data['error_penalty']);
        }

        if (isset($data['priority'])) {
            $result->setPriority((float) $data['priority']);
        }

        if (isset($data['urgency'])) {
            $result->setUrgency((float) $data['urgency']);
        }

        if (isset($data['cost_factor'])) {
            $result->setCostFactor((float) $data['cost_factor']);
        }

        return $result;
    }
}
