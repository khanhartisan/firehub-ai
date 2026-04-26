<?php

namespace App\Contracts\Synthesizer\Author;

use App\Concerns\Serializable as SerializableTrait;
use App\Contracts\DOM\Article;
use App\Contracts\Serializable;
use App\Models\File;
use Illuminate\Database\Eloquent\Collection;

/**
 * Synthesizer draft output: title, excerpt, article DOM, and referenced file IDs.
 */
final class Draft implements Serializable
{
    use SerializableTrait;

    protected ?string $title = null;

    protected ?string $excerpt = null;

    protected ?Article $article = null;

    /**
     * @var string[]
     */
    protected array $referenceFileIds = [];

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): static
    {
        $this->excerpt = $excerpt;

        return $this;
    }

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
     * @return string[]
     */
    public function getReferenceFileIds(): array
    {
        return $this->referenceFileIds;
    }

    /**
     * @param  string[]  $referenceFileIds
     */
    public function setReferenceFileIds(array $referenceFileIds): static
    {
        $this->referenceFileIds = array_values(array_map(static fn ($id) => (string) $id, $referenceFileIds));

        return $this;
    }

    /**
     * @return Collection<File>
     */
    public function getReferenceFiles(): Collection
    {
        if (! $this->getReferenceFileIds()) {
            return new Collection;
        }

        return File::query()->whereIn('id', $this->getReferenceFileIds())->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->getTitle(),
            'excerpt' => $this->getExcerpt(),
            'article' => $this->getArticle()?->toArray(),
            'reference_file_ids' => $this->getReferenceFileIds(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $draft = new static;

        if (isset($data['title'])) {
            $draft->setTitle($data['title'] !== null ? (string) $data['title'] : null);
        }

        if (isset($data['excerpt'])) {
            $draft->setExcerpt($data['excerpt'] !== null ? (string) $data['excerpt'] : null);
        }

        if (isset($data['article']) && is_array($data['article'])) {
            $draft->setArticle(Article::fromArray($data['article']));
        }

        if (isset($data['reference_file_ids']) && is_array($data['reference_file_ids'])) {
            $draft->setReferenceFileIds($data['reference_file_ids']);
        }

        return $draft;
    }
}
