<?php

namespace App\Contracts\CommonData\Concerns;

trait HasRelevance
{
    protected ?float $relevance = null;

    public function getRelevance(): ?float
    {
        return $this->relevance;
    }

    public function setRelevance(?float $relevance): static
    {
        $relevance = round($relevance ?? 0, 2);
        $relevance = min($relevance, 1);
        $relevance = max($relevance, 0);
        $this->relevance = $relevance;

        return $this;
    }

    protected function hydrateRelevance(array $data): static
    {
        if (array_key_exists('relevance', $data)) {
            $relevance = $data['relevance'];
            if ($relevance === null) {
                $this->setRelevance(null);
            } elseif (is_int($relevance) || is_float($relevance)) {
                $this->setRelevance((float) $relevance);
            } elseif (is_string($relevance) && is_numeric($relevance)) {
                $this->setRelevance((float) $relevance);
            }
        }

        return $this;
    }
}
