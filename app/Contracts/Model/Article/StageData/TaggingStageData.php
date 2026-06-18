<?php

namespace App\Contracts\Model\Article\StageData;

use App\Concerns\Serializable;

final class TaggingStageData implements \App\Contracts\Serializable
{
    use Serializable;

    /** @var string[] */
    protected array $suggestedTags = [];

    /**
     * @return string[]
     */
    public function getSuggestedTags(): array
    {
        return $this->suggestedTags;
    }

    public function hasSuggestedTags(): bool
    {
        return $this->suggestedTags !== [];
    }

    /**
     * @param  string[]  $suggestedTags
     */
    public function setSuggestedTags(array $suggestedTags): static
    {
        $normalized = [];
        foreach ($suggestedTags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }

            $normalized[] = $tag;
        }

        $this->suggestedTags = array_values(array_unique($normalized));

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'suggested_tags' => $this->getSuggestedTags(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $dto = new static;

        if (isset($data['suggested_tags']) && is_array($data['suggested_tags'])) {
            $dto->setSuggestedTags($data['suggested_tags']);
        }

        return $dto;
    }
}
