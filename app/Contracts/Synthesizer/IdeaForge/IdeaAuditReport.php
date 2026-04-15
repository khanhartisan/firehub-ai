<?php

namespace App\Contracts\Synthesizer\IdeaForge;

use App\Contracts\Identifiable;
use App\Contracts\Serializable;
use Illuminate\Support\Str;

final class IdeaAuditReport implements Identifiable, Serializable
{
    use \App\Concerns\Identifiable;
    use \App\Concerns\Serializable;

    protected Idea $idea;

    /**
     * @var float|null Ranging from 0.00 to 1.00, higher is better
     */
    protected ?float $score = null;

    /**
     * A list of positive aspects and key selling points of the idea.
     *
     * @var string[]
     */
    protected array $highlights = [];

    /**
     * Potential risks, logical flaws, or areas that require improvement.
     *
     * @var string[]
     */
    protected array $concerns = [];

    public function __construct(Idea $idea, ?float $score = null, array $highlights = [], array $concerns = [])
    {
        $this->idea = $idea;
        $this->score = $score;
        $this->setHighlights($highlights);
        $this->setConcerns($concerns);
        $this->setIdentifier(Str::uuid()->toString());
    }

    public function getIdea(): Idea
    {
        return $this->idea;
    }

    public function setIdea(Idea $idea): static
    {
        $this->idea = $idea;

        return $this;
    }

    /**
     * @return float|null Ranging from 0.00 to 1.00, higher is better
     */
    public function getScore(): ?float
    {
        return $this->score;
    }

    /**
     * @param  float|null  $score Ranging from 0.00 to 1.00, higher is better
     */
    public function setScore(?float $score): static
    {
        $this->score = $score;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getHighlights(): array
    {
        return $this->highlights;
    }

    /**
     * @param  string[]  $highlights
     */
    public function setHighlights(array $highlights): static
    {
        $this->highlights = array_values(array_map(static fn ($item) => (string) $item, $highlights));

        return $this;
    }

    /**
     * @return string[]
     */
    public function getConcerns(): array
    {
        return $this->concerns;
    }

    /**
     * @param  string[]  $concerns
     */
    public function setConcerns(array $concerns): static
    {
        $this->concerns = array_values(array_map(static fn ($item) => (string) $item, $concerns));

        return $this;
    }

    public function toArray(): array
    {
        return [
            'identifier' => $this->idea->getIdentifier(),
            'idea' => $this->getIdea()->toArray(),
            'score' => $this->getScore(),
            'highlights' => $this->getHighlights(),
            'concerns' => $this->getConcerns(),
        ];
    }

    public static function fromArray(array $data): static
    {
        if (! array_key_exists('idea', $data)) {
            throw new \Exception('idea must be set');
        }

        $rawIdea = $data['idea'];
        $idea = null;
        if ($rawIdea instanceof Idea) {
            $idea = $rawIdea;
        } elseif (is_array($rawIdea)) {
            $idea = Idea::fromArray($rawIdea);
        }

        if (! $idea instanceof Idea) {
            throw new \Exception('idea is invalid');
        }

        $report = new static($idea);

        if (array_key_exists('score', $data)) {
            $report->setScore($data['score'] !== null ? (float) $data['score'] : null);
        }

        if (isset($data['highlights']) && is_array($data['highlights'])) {
            $report->setHighlights($data['highlights']);
        }

        if (isset($data['concerns']) && is_array($data['concerns'])) {
            $report->setConcerns($data['concerns']);
        }

        if (array_key_exists('identifier', $data)) {
            $report->setIdentifier($data['identifier'] !== null ? (string) $data['identifier'] : null);
        }

        return $report;
    }
}