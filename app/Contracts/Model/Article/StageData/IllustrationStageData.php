<?php

namespace App\Contracts\Model\Article\StageData;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\Serializable;
use App\Contracts\Model\Article\StageData\IllustrationStageData\IllustrationTask;
use App\Contracts\Synthesizer\Author\IllustrationAnchor;
use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Contracts\Synthesizer\Illustration\IllustrationDirection;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;

final class IllustrationStageData implements Serializable
{
    use SerializableTrait;

    /** @var IllustrationTask[] */
    protected array $illustrationTasks = [];

    /** @var IllustrationResult[] */
    protected array $illustrationResults = [];

    /** @var IllustrationAnchor[] */
    protected array $illustrationAnchors = [];

    /**
     * @return IllustrationTask[]
     */
    public function getIllustrationTasks(): array
    {
        return $this->illustrationTasks;
    }

    /**
     * @param  IllustrationTask[]  $illustrationTasks
     */
    public function setIllustrationTasks(array $illustrationTasks): static
    {
        $filtered = array_values(
            array_filter($illustrationTasks, static fn ($t) => $t instanceof IllustrationTask && $t->getIllustrationContext() instanceof IllustrationContext)
        );
        $this->illustrationTasks = $filtered;

        return $this;
    }

    /**
     * @return IllustrationContext[]
     */
    public function getIllustrationContexts(): array
    {
        return array_values(
            array_filter(
                array_map(static fn (IllustrationTask $task) => $task->getIllustrationContext(), $this->illustrationTasks),
                static fn ($context) => $context instanceof IllustrationContext
            )
        );
    }

    /**
     * @param  IllustrationContext[]  $illustrationContexts
     */
    public function setIllustrationContexts(array $illustrationContexts): static
    {
        $filteredContexts = array_values(
            array_filter($illustrationContexts, static fn ($c) => $c instanceof IllustrationContext)
        );
        $existingDirectionsByContextIdentifier = [];
        foreach ($this->illustrationTasks as $task) {
            $context = $task->getIllustrationContext();
            $direction = $task->getIllustrationDirection();
            if (! $context instanceof IllustrationContext || ! $direction instanceof IllustrationDirection) {
                continue;
            }

            $existingDirectionsByContextIdentifier[$context->getIdentifier()] = $direction;
        }

        $tasks = [];
        foreach ($filteredContexts as $context) {
            $tasks[] = (new IllustrationTask)
                ->setIllustrationContext($context)
                ->setIllustrationDirection($existingDirectionsByContextIdentifier[$context->getIdentifier()] ?? null);
        }
        $this->setIllustrationTasks($tasks);

        return $this;
    }

    /**
     * @return IllustrationDirection[]
     */
    public function getIllustrationDirections(): array
    {
        return array_values(
            array_filter(
                array_map(static fn (IllustrationTask $task) => $task->getIllustrationDirection(), $this->illustrationTasks),
                static fn ($direction) => $direction instanceof IllustrationDirection
            )
        );
    }

    public function getIllustrationDirectionByContextIdentifier(string $contextIdentifier): ?IllustrationDirection
    {
        foreach ($this->illustrationTasks as $task) {
            $context = $task->getIllustrationContext();
            if (! $context instanceof IllustrationContext || $context->getIdentifier() !== $contextIdentifier) {
                continue;
            }

            return $task->getIllustrationDirection();
        }

        return null;
    }

    public function setIllustrationDirectionForContextIdentifier(string $contextIdentifier, IllustrationDirection $direction): static
    {
        foreach ($this->illustrationTasks as $task) {
            $context = $task->getIllustrationContext();
            if (! $context instanceof IllustrationContext || $context->getIdentifier() !== $contextIdentifier) {
                continue;
            }

            $task->setIllustrationDirection($direction);

            return $this;
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
        if ($this->illustrationTasks !== [] && $this->illustrationResults === []) {
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
            'illustration_tasks' => array_map(static fn (IllustrationTask $t) => $t->toArray(), $this->illustrationTasks),
            'illustration_results' => array_map(static fn (IllustrationResult $r) => $r->toArray(), $this->illustrationResults),
            'illustration_anchors' => array_map(static fn (IllustrationAnchor $a) => $a->toArray(), $this->illustrationAnchors),
        ];
    }

    public static function fromArray(array $data): static
    {
        $obj = new static;

        if (isset($data['illustration_tasks']) && is_array($data['illustration_tasks'])) {
            $tasks = [];
            foreach ($data['illustration_tasks'] as $task) {
                if (is_array($task)) {
                    $tasks[] = IllustrationTask::fromArray($task);
                }
            }
            $obj->setIllustrationTasks($tasks);
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
