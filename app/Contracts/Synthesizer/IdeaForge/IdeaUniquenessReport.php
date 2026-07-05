<?php

namespace App\Contracts\Synthesizer\IdeaForge;

use App\Contracts\Serializable;
use App\Models\Article;

final class IdeaUniquenessReport implements Serializable
{
    use HasIdeaIdentifier;
    use \App\Concerns\Serializable;

    protected ?string $clientId = null;

    protected ?bool $isUnique = null;

    protected ?float $similarity = null;

    /**
     * @var Article[]
     */
    protected array $similarArticles = [];

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): static
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getIsUnique(): ?bool
    {
        return $this->isUnique;
    }

    public function setIsUnique(?bool $isUnique): static
    {
        $this->isUnique = $isUnique;

        return $this;
    }

    public function getSimilarity(): ?float
    {
        return $this->similarity;
    }

    public function setSimilarity(?float $similarity): static
    {
        $this->similarity = $similarity;

        return $this;
    }

    /**
     * @return Article[]
     */
    public function getSimilarArticles(): array
    {
        return $this->similarArticles;
    }

    /**
     * @param  Article[]  $similarArticles
     */
    public function setSimilarArticles(array $similarArticles): static
    {
        $this->similarArticles = array_values(array_filter($similarArticles, static fn ($article) => $article instanceof Article));

        return $this;
    }

    public function toArray(): array
    {
        return [
            'client_id' => $this->getClientId(),
            'idea_identifier' => $this->getIdeaIdentifier(),
            'is_unique' => $this->getIsUnique(),
            'similarity' => $this->getSimilarity(),
            'similar_articles' => array_map(static fn (Article $article) => $article->toArray(), $this->getSimilarArticles()),
        ];
    }

    public static function fromArray(array $data): static
    {
        $report = new static;

        if (array_key_exists('client_id', $data) && $data['client_id'] !== null) {
            $report->setClientId((string) $data['client_id']);
        } else {
            throw new \Exception('client_id must be set');
        }

        if (array_key_exists('idea_identifier', $data) && $data['idea_identifier'] !== null && $data['idea_identifier'] !== '') {
            $report->setIdeaIdentifier((string) $data['idea_identifier']);
        }

        if (array_key_exists('is_unique', $data)) {
            $report->setIsUnique($data['is_unique'] !== null ? (bool) $data['is_unique'] : null);
        }

        if (array_key_exists('similarity', $data)) {
            $report->setSimilarity($data['similarity'] !== null ? (float) $data['similarity'] : null);
        }

        if (isset($data['similar_articles']) && is_array($data['similar_articles'])) {
            $articleIds = [];
            foreach ($data['similar_articles'] as $item) {
                if ($item instanceof Article && $item->getKey() !== null) {
                    $articleIds[] = (string) $item->getKey();
                    continue;
                }

                if (is_array($item) && array_key_exists('id', $item) && is_scalar($item['id'])) {
                    $articleIds[] = (string) $item['id'];
                    continue;
                }

                if (is_scalar($item)) {
                    $articleIds[] = (string) $item;
                }
            }

            $articleIds = array_values(array_unique(array_filter($articleIds, static fn (string $id) => $id !== '')));

            if ($articleIds !== []) {
                $articles = Article::query()
                    ->whereIn('id', $articleIds)
                    ->get()
                    ->toArray();

                $report->setSimilarArticles($articles);
            }
        }

        return $report;
    }
}