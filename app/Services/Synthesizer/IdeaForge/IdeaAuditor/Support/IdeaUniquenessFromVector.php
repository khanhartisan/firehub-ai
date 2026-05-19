<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAuditor\Support;

use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\VectorDB\SearchOptions;
use App\Contracts\VectorDB\SearchResult;
use App\Facades\TextEmbedding;
use App\Facades\VectorDB;
use App\Models\Article;

/**
 * Helpers for OpenAI-driven uniqueness: {@see OpenAIIdeaAuditorDriver} uses
 * {@see searchTextForIdea()} plus {@see candidateArticlesWithSimilarityScores()} (VectorDB
 * retrieval with scores). Config under `synthesizer.idea_auditor.uniqueness` applies to limits.
 */
final class IdeaUniquenessFromVector
{
    /**
     * Text embedded for vector search (title + description when both present).
     */
    public static function searchTextForIdea(Idea $idea): string
    {
        $intent = $idea->getIntent();
        $title = trim((string) ($intent->getTitle() ?? ''));
        $description = trim((string) ($intent->getDescription() ?? ''));

        if ($title !== '' && $description !== '') {
            return $title."\n\n".$description;
        }

        return $title !== '' ? $title : $description;
    }

    /**
     * Nearest-neighbor articles for the idea text, with vector scores (for the OpenAI prompt).
     * Hits are deduped by id; order follows the vector index (best first).
     *
     * @return list<array{article: Article, score: float}>
     */
    public static function candidateArticlesWithSimilarityScores(string $clientId, string $text, int $limit): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $limit = max(1, min(1000, $limit));
        $model = new Article;

        $searchResults = VectorDB::search(
            $model->getVectorIndex(),
            TextEmbedding::embed($text),
            new SearchOptions(
                $limit,
                ['client_id' => $clientId],
                null,
            )
        );

        if ($searchResults === []) {
            return [];
        }

        $orderedUniqueResults = [];
        $seen = [];
        foreach ($searchResults as $sr) {
            $id = (string) $sr->record->id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $orderedUniqueResults[] = $sr;
        }

        $rows = Article::query()
            ->where('client_id', $clientId)
            ->whereIn(
                $model->getQualifiedKeyName(),
                array_map(static fn (SearchResult $sr) => (string) $sr->record->id, $orderedUniqueResults)
            )
            ->get()
            ->keyBy(static fn (Article $a) => (string) $a->getKey());

        $out = [];
        foreach ($orderedUniqueResults as $sr) {
            $id = (string) $sr->record->id;
            $article = $rows->get($id);
            if (! $article instanceof Article) {
                continue;
            }
            $out[] = ['article' => $article, 'score' => $sr->score];
        }

        return $out;
    }
}
