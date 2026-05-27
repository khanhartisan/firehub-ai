<?php

namespace App\Contracts\Model\Article\StageData;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Model\Article\StageData\RectificationStageData\CriticRectificationState;
use App\Contracts\Serializable;
use App\Contracts\Synthesizer\Critic\Rectification;

final class RectificationStageData implements Serializable
{
    use SerializableTrait;

    /** @var array<string, CriticRectificationState> keyed by purpose */
    protected array $critics = [];

    public function getMaxCriticRound(): int
    {
        $max = 0;
        foreach ($this->critics as $state) {
            $max = max($max, $state->getRound());
        }

        return $max;
    }

    public function hasReachedMaxRounds(int $maxRounds): bool
    {
        return $this->getMaxCriticRound() >= $maxRounds;
    }

    /**
     * @param  list<array{purpose: string, order: int}>  $configured
     */
    public function ensureCriticsInitialized(array $configured): static
    {
        $expected = [];
        foreach ($configured as $row) {
            $purpose = (string) ($row['purpose'] ?? '');
            if ($purpose === '') {
                continue;
            }

            $expected[$purpose] = (int) ($row['order'] ?? 0);
        }

        if ($this->critics !== [] && $this->configuredCriticsMatch($expected)) {
            return $this;
        }

        $this->critics = [];

        foreach ($expected as $purpose => $order) {
            $this->critics[$purpose] = (new CriticRectificationState)
                ->setPurpose($purpose)
                ->setOrder($order);
        }

        return $this;
    }

    /**
     * @param  array<string, int>  $expected
     */
    protected function configuredCriticsMatch(array $expected): bool
    {
        if (count($this->critics) !== count($expected)) {
            return false;
        }

        foreach ($expected as $purpose => $order) {
            $state = $this->critics[$purpose] ?? null;
            if (! $state instanceof CriticRectificationState || $state->getOrder() !== $order) {
                return false;
            }
        }

        return true;
    }

    public function getCriticState(string $purpose): ?CriticRectificationState
    {
        return $this->critics[$purpose] ?? null;
    }

    public function getCriticAwaitingRectification(): ?CriticRectificationState
    {
        foreach ($this->sortedCritics() as $state) {
            if ($state->getPendingCriticisms() !== []) {
                return $state;
            }
        }

        return null;
    }

    public function getNextPendingCritic(): ?CriticRectificationState
    {
        if ($this->getCriticAwaitingRectification() !== null) {
            return null;
        }

        foreach ($this->sortedCritics() as $state) {
            if (! $state->isFinished() && $state->getPendingCriticisms() === []) {
                return $state;
            }
        }

        return null;
    }

    public function allCriticsVisitedThisPass(): bool
    {
        return $this->getNextPendingCritic() === null && $this->getCriticAwaitingRectification() === null;
    }

    /**
     * @return list<CriticRectificationState>
     */
    protected function sortedCritics(): array
    {
        $critics = array_values($this->critics);

        usort(
            $critics,
            static function (CriticRectificationState $a, CriticRectificationState $b): int {
                $byOrder = $a->getOrder() <=> $b->getOrder();
                if ($byOrder !== 0) {
                    return $byOrder;
                }

                return $a->getPurpose() <=> $b->getPurpose();
            }
        );

        return $critics;
    }

    /**
     * @param  array<\App\Contracts\Synthesizer\Critic\Criticism>  $criticisms
     */
    public function flagCriticAwaitingRectification(string $purpose, array $criticisms): static
    {
        $state = $this->critics[$purpose] ?? null;
        if (! $state instanceof CriticRectificationState) {
            return $this;
        }

        $state->setPendingCriticisms($criticisms);
        $state->incrementRound();

        return $this;
    }

    public function markCriticDone(string $purpose): static
    {
        $state = $this->critics[$purpose] ?? null;
        if (! $state instanceof CriticRectificationState) {
            return $this;
        }

        $state->setPendingCriticisms([]);
        $state->setFinished(true);

        return $this;
    }

    public function advancePass(): static
    {
        foreach ($this->critics as $state) {
            $state->incrementRound();
        }

        return $this;
    }

    public function resetForNextPass(): static
    {
        foreach ($this->critics as $state) {
            $state->setPendingCriticisms([]);
            $state->setFinished(false);
        }

        return $this;
    }

    /**
     * All writer fixes so far, in critic run order (for last_rectifications).
     *
     * @return Rectification[]
     */
    public function getRectifications(): array
    {
        $rectifications = [];

        foreach ($this->sortedCritics() as $state) {
            foreach ($state->getRectifications() as $rectification) {
                $rectifications[] = $rectification;
            }
        }

        return $rectifications;
    }

    /**
     * @param  Rectification[]  $rectifications
     */
    public function addRectificationsForCritic(string $purpose, array $rectifications): static
    {
        $state = $this->critics[$purpose] ?? null;
        if ($state instanceof CriticRectificationState) {
            $state->addRectifications($rectifications);
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'critics' => array_map(
                static fn (CriticRectificationState $s): array => $s->toArray(),
                $this->sortedCritics()
            ),
        ];
    }

    public static function fromArray(array $data): static
    {
        $obj = new static;

        if (isset($data['critics']) && is_array($data['critics'])) {
            foreach ($data['critics'] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $state = CriticRectificationState::fromArray($row);
                $purpose = $state->getPurpose();
                if ($purpose !== '') {
                    $obj->critics[$purpose] = $state;
                }
            }
        }

        return $obj;
    }
}
