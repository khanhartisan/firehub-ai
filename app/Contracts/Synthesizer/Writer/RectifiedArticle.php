<?php

namespace App\Contracts\Synthesizer\Writer;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\DOM\Article;
use App\Contracts\Serializable;
use App\Contracts\Synthesizer\Critic\Rectification;

/**
 * An article after applying critic feedback, plus the rectifications that were applied.
 */
final class RectifiedArticle implements Serializable
{
    use SerializableTrait;

    protected ?Article $article = null;

    /**
     * @var Rectification[]
     */
    protected array $rectifications = [];

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;

        return $this;
    }

    /**
     * @return Rectification[]
     */
    public function getRectifications(): array
    {
        return $this->rectifications;
    }

    public function addRectification(Rectification $rectification): static
    {
        $this->rectifications[] = $rectification;

        return $this;
    }

    /**
     * @param  Rectification[]  $rectifications
     */
    public function setRectifications(array $rectifications): static
    {
        $this->rectifications = [];
        foreach ($rectifications as $index => $rectification) {
            if (! $rectification instanceof Rectification) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'rectifications[%s] must be an instance of %s, %s given.',
                        $index,
                        Rectification::class,
                        get_debug_type($rectification)
                    )
                );
            }

            $this->rectifications[] = $rectification;
        }

        return $this;
    }

    public function hydrateRectifications(array $data): static
    {
        if (! isset($data['rectifications']) || ! is_array($data['rectifications'])) {
            return $this;
        }

        $hydratedRectifications = [];
        foreach ($data['rectifications'] as $row) {
            if ($row instanceof Rectification) {
                $hydratedRectifications[] = $row;

                continue;
            }

            if (is_array($row)) {
                $hydratedRectifications[] = Rectification::fromArray($row);
            }
        }

        return $this->setRectifications($hydratedRectifications);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'article' => $this->getArticle()?->toArray(),
            'rectifications' => array_map(
                static fn (Rectification $rectification): array => $rectification->toArray(),
                $this->getRectifications()
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $rectifiedArticle = new static;

        if (isset($data['article']) && is_array($data['article'])) {
            $rectifiedArticle->setArticle(Article::fromArray($data['article']));
        }

        return $rectifiedArticle->hydrateRectifications($data);
    }
}
