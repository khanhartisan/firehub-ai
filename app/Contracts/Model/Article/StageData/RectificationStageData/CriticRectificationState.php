<?php

namespace App\Contracts\Model\Article\StageData\RectificationStageData;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Contracts\Synthesizer\Critic\Rectification;

final class CriticRectificationState implements Serializable
{
    use SerializableTrait;

    protected string $purpose = '';

    protected int $order = 0;

    protected bool $finished = false;

    protected int $round = 0;

    protected int $maxRectificationRounds = 1;

    /** @var Criticism[] */
    protected array $pendingCriticisms = [];

    /** @var Rectification[] */
    protected array $rectifications = [];

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): static
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): static
    {
        $this->order = max(0, $order);

        return $this;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function setFinished(bool $finished): static
    {
        $this->finished = $finished;

        return $this;
    }

    public function getRound(): int
    {
        return $this->round;
    }

    public function setRound(int $round): static
    {
        $this->round = max(0, $round);

        return $this;
    }

    public function incrementRound(): static
    {
        $this->round = max(0, $this->round + 1);

        return $this;
    }

    public function getMaxRectificationRounds(): int
    {
        return max(1, $this->maxRectificationRounds);
    }

    public function setMaxRectificationRounds(int $maxRectificationRounds): static
    {
        $this->maxRectificationRounds = max(1, $maxRectificationRounds);

        return $this;
    }

    public function hasReachedMaxRounds(): bool
    {
        return $this->round >= $this->getMaxRectificationRounds();
    }

    public function canRunAnotherPass(): bool
    {
        return ! $this->hasReachedMaxRounds();
    }

    /**
     * @return Criticism[]
     */
    public function getPendingCriticisms(): array
    {
        return $this->pendingCriticisms;
    }

    /**
     * @param  Criticism[]  $pendingCriticisms
     */
    public function setPendingCriticisms(array $pendingCriticisms): static
    {
        $this->pendingCriticisms = array_values(
            array_filter($pendingCriticisms, static fn ($c) => $c instanceof Criticism)
        );

        return $this;
    }

    /**
     * @return Rectification[]
     */
    public function getRectifications(): array
    {
        return $this->rectifications;
    }

    /**
     * @param  Rectification[]  $rectifications
     */
    public function addRectifications(array $rectifications): static
    {
        foreach ($rectifications as $rectification) {
            if ($rectification instanceof Rectification) {
                $this->rectifications[] = $rectification;
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'purpose' => $this->purpose,
            'order' => $this->order,
            'finished' => $this->finished ?: null,
            'round' => $this->round > 0 ? $this->round : null,
            'max_rectification_rounds' => $this->maxRectificationRounds !== 1
                ? $this->maxRectificationRounds
                : null,
            'pending_criticisms' => $this->pendingCriticisms === []
                ? null
                : array_map(
                    static fn (Criticism $criticism): array => $criticism->toArray(),
                    $this->pendingCriticisms
                ),
            'rectifications' => $this->rectifications === []
                ? null
                : array_map(
                    static fn (Rectification $rectification): array => $rectification->toArray(),
                    $this->rectifications
                ),
        ], static fn (mixed $value): bool => $value !== null);
    }

    public static function fromArray(array $data): static
    {
        $state = new static;

        if (isset($data['purpose'])) {
            $state->setPurpose((string) $data['purpose']);
        }

        if (isset($data['order'])) {
            $state->setOrder((int) $data['order']);
        }

        if (! empty($data['finished'])) {
            $state->setFinished(true);
        }

        if (isset($data['round'])) {
            $state->setRound((int) $data['round']);
        }

        if (isset($data['max_rectification_rounds'])) {
            $state->setMaxRectificationRounds((int) $data['max_rectification_rounds']);
        }

        if (isset($data['pending_criticisms']) && is_array($data['pending_criticisms'])) {
            $pendingCriticisms = [];
            foreach ($data['pending_criticisms'] as $row) {
                if (is_array($row)) {
                    $pendingCriticisms[] = Criticism::fromArray($row);
                }
            }
            $state->setPendingCriticisms($pendingCriticisms);
        }

        if (isset($data['rectifications']) && is_array($data['rectifications'])) {
            $rectifications = [];
            foreach ($data['rectifications'] as $row) {
                if (is_array($row)) {
                    $rectifications[] = Rectification::fromArray($row);
                }
            }
            $state->addRectifications($rectifications);
        }

        return $state;
    }
}
