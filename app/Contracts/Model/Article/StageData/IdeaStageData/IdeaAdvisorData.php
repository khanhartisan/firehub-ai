<?php

namespace App\Contracts\Model\Article\StageData\IdeaStageData;

use App\Concerns\Serializable;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Contracts\Synthesizer\IdeaForge\TemporalSuggestion;

final class IdeaAdvisorData implements \App\Contracts\Serializable
{
    use Serializable;

    protected ?string $advisorDescription = null;

    /** @var TemporalSuggestion[] */
    protected array $temporalSuggestions = [];

    /** @var IntentTypeSuggestion[] */
    protected array $intentTypeSuggestions = [];

    /** @var Idea[] */
    protected array $ideas = [];

    public int $ideaIndex = 0;

    /** @var IdeaReviewData[] */
    protected array $ideaReviews = [];

    public function getAdvisorDescription(): ?string
    {
        return $this->advisorDescription;
    }

    public function setAdvisorDescription(?string $advisorDescription): static
    {
        $this->advisorDescription = $advisorDescription !== null
            ? (string) $advisorDescription
            : null;

        return $this;
    }

    public function getTemporalSuggestions(): array
    {
        return $this->temporalSuggestions;
    }

    public function setTemporalSuggestions(array $temporalSuggestions): static
    {
        $this->temporalSuggestions = array_values(array_filter(
            $temporalSuggestions,
            static fn ($v): bool => $v instanceof TemporalSuggestion
        ));

        return $this;
    }

    public function getIntentTypeSuggestions(): array
    {
        return $this->intentTypeSuggestions;
    }

    public function setIntentTypeSuggestions(array $intentTypeSuggestions): static
    {
        $this->intentTypeSuggestions = array_values(array_filter(
            $intentTypeSuggestions,
            static fn ($v): bool => $v instanceof IntentTypeSuggestion
        ));

        return $this;
    }

    public function getIdeas(): array
    {
        return $this->ideas;
    }

    public function setIdeas(array $ideas): static
    {
        $this->ideas = array_values(array_filter(
            $ideas,
            static fn ($v): bool => $v instanceof Idea
        ));

        return $this;
    }

    public function getIdeaIndex(): int
    {
        return max(0, $this->ideaIndex);
    }

    public function setIdeaIndex(int $ideaIndex): static
    {
        $this->ideaIndex = max(0, $ideaIndex);

        return $this;
    }

    /** @return IdeaReviewData[] */
    public function getIdeaReviews(): array
    {
        return $this->ideaReviews;
    }

    public function setIdeaReviews(array $ideaReviews): static
    {
        $this->ideaReviews = array_values(array_filter(
            $ideaReviews,
            static fn ($v): bool => $v instanceof IdeaReviewData
        ));

        return $this;
    }

    public function getIdeaReview(int $index): IdeaReviewData
    {
        return $this->ideaReviews[$index] ?? new IdeaReviewData;
    }

    public function setIdeaReview(int $index, IdeaReviewData $review): static
    {
        $this->ideaReviews[$index] = $review;
        ksort($this->ideaReviews);

        return $this;
    }

    public function toArray(): array
    {
        return [
            'advisor_description' => $this->getAdvisorDescription(),
            'temporal_suggestions' => array_map(static fn (TemporalSuggestion $v) => $v->toArray(), $this->temporalSuggestions),
            'intent_type_suggestions' => array_map(static fn (IntentTypeSuggestion $v) => $v->toArray(), $this->intentTypeSuggestions),
            'ideas' => array_map(static fn (Idea $v) => $v->toArray(), $this->ideas),
            'idea_index' => $this->getIdeaIndex(),
            'idea_reviews' => array_map(static fn (IdeaReviewData $v) => $v->toArray(), $this->ideaReviews),
        ];
    }

    public static function fromArray(array $data): static
    {
        $dto = new static;

        if (array_key_exists('advisor_description', $data)) {
            $dto->setAdvisorDescription(
                $data['advisor_description'] !== null ? (string) $data['advisor_description'] : null
            );
        }

        if (isset($data['temporal_suggestions']) && is_array($data['temporal_suggestions'])) {
            $dto->setTemporalSuggestions(array_map(
                static fn (array $v): TemporalSuggestion => TemporalSuggestion::fromArray($v),
                array_values(array_filter($data['temporal_suggestions'], 'is_array'))
            ));
        }

        if (isset($data['intent_type_suggestions']) && is_array($data['intent_type_suggestions'])) {
            $dto->setIntentTypeSuggestions(array_map(
                static fn (array $v): IntentTypeSuggestion => IntentTypeSuggestion::fromArray($v),
                array_values(array_filter($data['intent_type_suggestions'], 'is_array'))
            ));
        }

        if (isset($data['ideas']) && is_array($data['ideas'])) {
            $dto->setIdeas(array_map(
                static fn (array $v): Idea => Idea::fromArray($v),
                array_values(array_filter($data['ideas'], 'is_array'))
            ));
        }

        $dto->setIdeaIndex((int) ($data['idea_index'] ?? 0));

        if (isset($data['idea_reviews']) && is_array($data['idea_reviews'])) {
            $dto->setIdeaReviews(array_map(
                static fn (array $v): IdeaReviewData => IdeaReviewData::fromArray($v),
                array_values(array_filter($data['idea_reviews'], 'is_array'))
            ));
        }

        return $dto;
    }
}
