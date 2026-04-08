<?php

namespace App\Utils;

use App\Contracts\VectorDB\SearchOptions;
use App\Contracts\VectorDB\SearchResult;
use App\Facades\TextEmbedding;
use App\Facades\VectorDB;
use App\Models\Article;
use App\Models\EmbeddableModel;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class VectorSearch
{
    public static function searchPages(string $searchString,
                                       int $limit = 100,
                                       float $scoreThreshold = 0.5): Collection
    {
        return static::search(
            new Page(),
            $searchString,
            [],
            $limit,
            $scoreThreshold
        );
    }

    public static function searchArticles(string $space,
                                          string $searchString,
                                          int $limit = 100,
                                          float $scoreThreshold = 0.5): Collection
    {
        return static::search(
            new Article(),
            $searchString,
            [
                'space' => $space,
            ],
            $limit,
            $scoreThreshold,
            function (Builder $query) use ($space) {
                $query->where('space', $space);
            }
        );
    }

    public static function search(EmbeddableModel $model,
                                  string $searchString,
                                  array $filter = [],
                                  int $limit = 100,
                                  float $scoreThreshold = 0.5,
                                  ?\Closure $queryModifier = null): Collection
    {
        $searchResults = VectorDB::search(
            $model->getVectorIndex(),
            TextEmbedding::embed($searchString),
            new SearchOptions(
                $limit,
                $filter,
                $scoreThreshold
            )
        );

        if (!$ids = array_unique(array_map(function (SearchResult $searchResult) {
            return $searchResult->record->id;
        }, $searchResults))) {
            return new Collection();
        }

        $query = $model->newQuery();
        if ($queryModifier) {
            $queryModifier($query);
        }

        return $query->whereIn($model->getQualifiedKeyName(), $ids)->get();
    }
}