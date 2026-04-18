<?php

namespace App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers;

use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport;
use App\Models\Article;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\IdeaAuditorService;
use Illuminate\Support\Collection;

class BasicIdeaAuditorDriver extends IdeaAuditorService
{
    protected ?string $uniquenessBaselineClientId = null;

    /** @var Collection<int, Article>|null */
    protected ?Collection $uniquenessBaselineArticles = null;

    public function isIdeaUnique(string $clientId, Idea $idea): IdeaUniquenessReport
    {
        return $this->buildUniquenessReport($clientId, $idea, $this->articlesForUniquenessBaseline($clientId));
    }

    /** @return Collection<int, Article> */
    protected function articlesForUniquenessBaseline(string $clientId): Collection
    {
        if ($this->uniquenessBaselineClientId !== $clientId || $this->uniquenessBaselineArticles === null) {
            $this->uniquenessBaselineClientId = $clientId;
            $this->uniquenessBaselineArticles = Article::query()
                ->where('client_id', $clientId)
                ->select(['id', 'title'])
                ->limit(20)
                ->get();
        }

        return $this->uniquenessBaselineArticles;
    }

    protected function buildUniquenessReport(string $clientId, Idea $idea, Collection $articles): IdeaUniquenessReport
    {
        $ideaTitle = (string) $idea->getIntent()->getTitle();

        $maxSimilarity = 0.0;
        $similarArticles = [];

        foreach ($articles as $article) {
            $score = $this->calculateSimilarity($ideaTitle, (string) $article->title);

            if ($score > 0.50) {
                $similarArticles[] = $article;
            }

            $maxSimilarity = max($maxSimilarity, $score);
        }

        return (new IdeaUniquenessReport)
            ->setClientId($clientId)
            ->setIdeaIdentifier(trim((string) $idea->getIdentifier()))
            ->setSimilarity($maxSimilarity)
            ->setIsUnique($maxSimilarity < 0.75)
            ->setSimilarArticles($similarArticles);
    }

    public function audit(Idea $idea): IdeaAuditReport
    {
        $confidence = $idea->getConfidence() ?? 0.5;
        $score = max(0.0, min(1.0, $confidence));

        $highlights = [
            sprintf('Temporal angle: %s.', $idea->getIntent()->getTemporal()?->value ?? 'n/a'),
            sprintf('Intent focus: %s.', implode(', ', array_map(static fn ($type) => $type->name, $idea->getIntent()->getTypes()))),
        ];

        $concerns = [];
        if ($score < 0.6) {
            $concerns[] = 'Low confidence idea. Consider adding more context constraints.';
        }

        if (trim((string) $idea->getIntent()->getDescription()) === '') {
            $concerns[] = 'Idea description is empty.';
        }

        return new IdeaAuditReport($idea, $score, $highlights, $concerns);
    }

    protected function calculateSimilarity(string $left, string $right): float
    {
        $left = mb_strtolower(trim($left));
        $right = mb_strtolower(trim($right));

        if ($left === '' || $right === '') {
            return 0.0;
        }

        similar_text($left, $right, $percent);

        return max(0.0, min(1.0, $percent / 100));
    }
}
