<?php

namespace App\Contracts\IntentResolver;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;

/**
 * One {@see Intentable} (e.g. a page or snippet) mapped to one or more {@see Intent} values,
 * each with a relevance score for how strongly that intent applies to the content.
 */
final class IntentableIntents implements Serializable
{
    use SerializableTrait;

    /** @var list<IntentableIntent> */
    protected array $intentableIntents = [];

    /**
     * @return list<IntentableIntent>
     */
    public function getIntentableIntents(): array
    {
        return $this->intentableIntents;
    }

    /**
     * @param  list<IntentableIntent>  $intentableIntents
     *
     * @throws \InvalidArgumentException When an element is not an {@see IntentableIntent} instance.
     */
    public function setIntentableIntents(array $intentableIntents): static
    {
        foreach ($intentableIntents as $index => $item) {
            if (! $item instanceof IntentableIntent) {
                throw new \InvalidArgumentException(
                    sprintf('intentable_intents[%s] must be an instance of %s, %s given.', $index, IntentableIntent::class, get_debug_type($item))
                );
            }
        }

        $this->intentableIntents = array_values($intentableIntents);

        return $this;
    }

    /**
     * Best single {@see Intent} for downstream steps that expect one: highest {@see IntentableIntent::getRelevance()},
     * then first row if all relevances are null.
     */
    public function getPrimaryIntent(): ?Intent
    {
        $rows = $this->intentableIntents;
        if ($rows === []) {
            return null;
        }

        usort($rows, function (IntentableIntent $a, IntentableIntent $b): int {
            $ra = $a->getRelevance();
            $rb = $b->getRelevance();
            if ($ra === null && $rb === null) {
                return 0;
            }
            if ($ra === null) {
                return 1;
            }
            if ($rb === null) {
                return -1;
            }

            return $rb <=> $ra;
        });

        return $rows[0]->getIntent();
    }

    /**
     * {@inheritdoc}
     *
     * @return array{intentable_intents: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'intentable_intents' => array_map(
                static fn (IntentableIntent $row): array => $row->toArray(),
                $this->intentableIntents,
            ),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        if (! isset($data['intentable_intents']) || ! is_array($data['intentable_intents'])) {
            throw new \InvalidArgumentException('IntentableIntents requires an "intentable_intents" array.');
        }

        $instance = new static;

        $rows = [];
        foreach ($data['intentable_intents'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            try {
                $rows[] = IntentableIntent::fromArray($row);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        if ($rows === []) {
            throw new \InvalidArgumentException('IntentableIntents requires at least one valid "intentable_intents" entry.');
        }

        $instance->setIntentableIntents($rows);

        return $instance;
    }
}
