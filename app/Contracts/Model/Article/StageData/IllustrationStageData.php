<?php

namespace App\Contracts\Model\Article\StageData;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use App\Contracts\Synthesizer\Author\IllustrationAnchor;
use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Contracts\Synthesizer\Illustration\IllustrationDirection;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;

final class IllustrationStageData implements Serializable
{
    use SerializableTrait;

    /** @var IllustrationContext[] */
    protected array $illustrationContexts = [];

    /** @var IllustrationDirection[] */
    protected array $illustrationDirections = [];

    /** @var IllustrationResult[] */
    protected array $illustrationResults = [];

    /** @var IllustrationAnchor[] */
    protected array $illustrationAnchors = [];

    /**
     * @return IllustrationContext[]
     */
    public function getIllustrationContexts(): array
    {
        return $this->illustrationContexts;
    }

    /**
     * @param  IllustrationContext[]  $illustrationContexts
     */
    public function setIllustrationContexts(array $illustrationContexts): static
    {
        $filtered = array_values(
            array_filter($illustrationContexts, static fn ($c) => $c instanceof IllustrationContext)
        );

        foreach ($filtered as $context) {
            $context->getIdentifier();
        }

        $this->illustrationContexts = $filtered;

        return $this;
    }

    /**
     * @return IllustrationDirection[]
     */
    public function getIllustrationDirections(): array
    {
        return $this->illustrationDirections;
    }

    /**
     * @param  IllustrationDirection[]  $illustrationDirections
     */
    public function setIllustrationDirections(array $illustrationDirections): static
    {
        $this->illustrationDirections = array_values(
            array_filter($illustrationDirections, static fn ($d) => $d instanceof IllustrationDirection)
        );

        return $this;
    }

    public function getIllustrationDirectionAt(int $index): ?IllustrationDirection
    {
        $direction = $this->illustrationDirections[$index] ?? null;

        return $direction instanceof IllustrationDirection ? $direction : null;
    }

    public function setIllustrationDirectionAt(int $index, IllustrationDirection $direction): static
    {
        $illustrationDirections = $this->illustrationDirections;
        $illustrationDirections[$index] = $direction;
        ksort($illustrationDirections);
        $this->setIllustrationDirections(array_values($illustrationDirections));

        return $this;
    }

    public function getIllustrationDirectionByContextIdentifier(string $contextIdentifier): ?IllustrationDirection
    {
        foreach ($this->getIllustrationContexts() as $index => $context) {
            if ($context->getIdentifier() !== $contextIdentifier) {
                continue;
            }

            return $this->getIllustrationDirectionAt($index);
        }

        return null;
    }

    public function setIllustrationDirectionForContextIdentifier(string $contextIdentifier, IllustrationDirection $direction): static
    {
        foreach ($this->getIllustrationContexts() as $index => $context) {
            if ($context->getIdentifier() !== $contextIdentifier) {
                continue;
            }

            return $this->setIllustrationDirectionAt($index, $direction);
        }

        return $this;
    }

    /**
     * @return IllustrationResult[]
     */
    public function getIllustrationResults(): array
    {
        return $this->illustrationResults;
    }

    /**
     * @param  IllustrationResult[]  $illustrationResults
     */
    public function setIllustrationResults(array $illustrationResults): static
    {
        $this->illustrationResults = array_values(
            array_filter($illustrationResults, static fn ($r) => $r instanceof IllustrationResult)
        );

        return $this;
    }

    public function addIllustrationResult(IllustrationResult $result): static
    {
        $this->illustrationResults[] = $result;

        return $this;
    }

    public function hasIllustrationResultForContextIdentifier(string $contextIdentifier): bool
    {
        foreach ($this->getIllustrationResults() as $result) {
            if ($result->getIllustrationContext()?->getIdentifier() === $contextIdentifier) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when there is nothing to anchor, or when persisted anchors account for every
     * illustration result identifier.
     */
    public function isIllustrationAnchorsResolved(): bool
    {
        if ($this->illustrationContexts !== [] && $this->illustrationResults === []) {
            return false;
        }

        $results = $this->getIllustrationResults();
        if ($results === []) {
            return true;
        }

        $anchored = [];
        foreach ($this->illustrationAnchors as $anchor) {
            if ($anchor instanceof IllustrationAnchor) {
                $anchored[$anchor->getIllustrationIdentifier()] = true;
            }
        }

        foreach ($results as $result) {
            if (! isset($anchored[$result->getIdentifier()])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return IllustrationAnchor[]
     */
    public function getIllustrationAnchors(): array
    {
        return $this->illustrationAnchors;
    }

    /**
     * @param  IllustrationAnchor[]  $illustrationAnchors
     */
    public function setIllustrationAnchors(array $illustrationAnchors): static
    {
        $this->illustrationAnchors = array_values(
            array_filter($illustrationAnchors, static fn ($a) => $a instanceof IllustrationAnchor)
        );

        return $this;
    }

    public function toArray(): array
    {
        return [
            'illustration_contexts' => array_map(
                static fn (IllustrationContext $c) => $c->toArray(),
                $this->illustrationContexts
            ),
            'illustration_directions' => array_map(static fn (IllustrationDirection $d) => $d->toArray(), $this->illustrationDirections),
            'illustration_results' => array_map(static fn (IllustrationResult $r) => $r->toArray(), $this->illustrationResults),
            'illustration_anchors' => array_map(static fn (IllustrationAnchor $a) => $a->toArray(), $this->illustrationAnchors),
        ];
    }

    public static function fromArray(array $data): static
    {
        $obj = new static;

        if (isset($data['illustration_contexts']) && is_array($data['illustration_contexts'])) {
            $contexts = [];
            foreach ($data['illustration_contexts'] as $c) {
                if (is_array($c)) {
                    $contexts[] = IllustrationContext::fromArray($c);
                }
            }
            $obj->setIllustrationContexts($contexts);
        }

        if (isset($data['illustration_directions']) && is_array($data['illustration_directions'])) {
            $illustrationDirections = [];
            foreach ($data['illustration_directions'] as $d) {
                if (is_array($d)) {
                    $illustrationDirections[] = IllustrationDirection::fromArray($d);
                }
            }
            $obj->setIllustrationDirections($illustrationDirections);
        }

        if (isset($data['illustration_results']) && is_array($data['illustration_results'])) {
            $illustrationResults = [];
            foreach ($data['illustration_results'] as $r) {
                if (is_array($r)) {
                    $illustrationResults[] = IllustrationResult::fromArray($r);
                }
            }
            $obj->setIllustrationResults($illustrationResults);
        }

        if (isset($data['illustration_anchors']) && is_array($data['illustration_anchors'])) {
            $illustrationAnchors = [];
            foreach ($data['illustration_anchors'] as $a) {
                if (is_array($a)) {
                    $illustrationAnchors[] = IllustrationAnchor::fromArray($a);
                }
            }
            $obj->setIllustrationAnchors($illustrationAnchors);
        }

        return $obj;
    }
}
