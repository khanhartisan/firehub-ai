<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\Model\Article\StageData;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Enums\ArticleStatus;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns\HandleIdeaStageBrainstorm;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns\HandleIdeaStageContext;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns\HandleIdeaStageIntentMerging;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns\HandleIdeaStageSuggestionCollection;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns\HandleIdeaStageUniquenessAndAudit;
use App\Models\Article;
use App\Models\Author;
use App\Utils\Str;

/**
 * IDEA stage: suggestions → weighted top picks → brainstorm → merge intents → uniqueness → audit → pick one idea → author context.
 *
 * Sub-steps are split across {@see HandleIdeaStageConcerns} traits. Most substeps use checkpoints:
 * return null after one expensive/persisted unit of work so the queue can run the next job
 * tick; return true when that phase is complete; false on hard failure.
 */
trait HandleIdeaStage
{
    use HandleIdeaStageContext;
    use HandleIdeaStageSuggestionCollection;
    use HandleIdeaStageBrainstorm;
    use HandleIdeaStageIntentMerging;
    use HandleIdeaStageUniquenessAndAudit;

    /**
     * @throws \Exception
     * @return ?true when the idea stage is complete and the job may advance to BRIEF; false on failure;
     *         null while still in-flight (checkpoint — job will be run again on the same stage).
     */
    protected function handleIdeaStage(): ?bool
    {
        if (! $this->article instanceof Article) {
            return false;
        }

        $context = $this->buildSemanticContext();
        if ($context === null) {
            return false;
        }

        // Check if we have a previous article is brainstorming
        if ($processingArticles = $this->client
            ->articles()
            ->where('status', ArticleStatus::PROCESSING)
            ->where('id', '<', $this->article->id)
            ->get() and $processingArticles->count()
            and $processingArticles->filter(function (Article $processingArticle) {
                if ($processingArticle->title) {
                    return false;
                }

                $ideaTitle = $processingArticle
                    ->stage_data
                    ?->getIdeaStageData()
                    ?->getPickedIdeaAuditReport()
                    ?->getIdea()
                    ?->getIntent()
                    ?->getTitle();

                return !$ideaTitle;
            })->count()
        ) {
            return null;
        }

        // Idea brainstorm context, appending the latest articles
        $ideaBrainstormContext = clone $context;
        if ($latestArticles = $this->client
            ->articles()
            ->whereIn('status', [
                ArticleStatus::PROCESSING,
                ArticleStatus::READY,
                ArticleStatus::PUBLISHED,
            ])
            ->take(100)
            ->orderBy('status')
            ->orderByDesc('id')
            ->get()
            ->map(function (Article $article) {
                if (!$article->title) {

                    /** @var StageData $stageData */
                    $stageData = $article->stage_data;
                    $ideaTitle = $stageData
                        ?->getIdeaStageData()
                        ?->getPickedIdeaAuditReport()
                        ?->getIdea()
                        ?->getIntent()
                        ?->getTitle();

                    if ($ideaTitle) {
                        $article->title = $ideaTitle;
                    }
                }
                return $article;
            })
            ->filter(function (Article $article) {
                return !! $article->title;
            })
            and $latestArticles->count()
        ) {
            $ideaBrainstormContext->set(
                'latest_article_titles',
                'Latest article for ideation context.',
                $latestArticles
                    ->map(function (Article $article) {
                        return [
                            'title' => Str::limit($article->title, 160),
                            'temporal' => $article->temporal?->value,
                            'created_at' => (string) $article->created_at
                        ];
                    })->values()->toArray()
            );
        } else {
            $ideaBrainstormContext->set('latest_article_titles', 'Latest article titles for ideation context.', ['No existing articles. This will be the first article.']);
        }

        // 1) Collect advisor suggestions.
        // Each costly step checkpoints and exits for re-execution.
        $suggestionProgress = $this->processSuggestionCollection($ideaBrainstormContext);
        if ($suggestionProgress !== true) {
            return $suggestionProgress;
        }

        // 2) Pick the best temporal and best intent (weighted by advisor; independent choices).
        $topSelection = $this->processTopSuggestionSelection();
        if ($topSelection !== true) {
            return $topSelection;
        }

        // 3) Brainstorm per advisor using the selected temporal and intent pair.
        $brainstormProgress = $this->processBrainstormCollection($ideaBrainstormContext);
        if ($brainstormProgress !== true) {
            return $brainstormProgress;
        }

        // 4) Merge similar intents (pairwise; state in idea.ideas + unique_idea_identifier_pairs).
        $mergeProgress = $this->processIntentMerging();
        if ($mergeProgress !== true) {
            return $mergeProgress;
        }

        // 5) Filter uniqueness
        $uniquenessProgress = $this->processUniquenessChecks();
        if ($uniquenessProgress !== true) {
            return $uniquenessProgress;
        }

        // 6) Audit surviving ideas.
        $auditProgress = $this->processAudits();
        if ($auditProgress !== true) {
            return $auditProgress;
        }

        $ideaData = $this->getIdeaStageData();
        $ideaAuditReports = $ideaData->getIdeaAuditReports();

        // 7) Pick one winning idea if not picked (limit 1).
        if (! $ideaData->getPickedIdeaAuditReport() instanceof IdeaAuditReport) {
            $pickedList = $this->getIdeaForgeService()->getIdeaPicker()->pick($ideaAuditReports, $ideaBrainstormContext, 1) ?? [];
            $pickedReport = $pickedList[0] ?? null;
            if (! $pickedReport instanceof IdeaAuditReport) {
                return false;
            }

            $ideaData->setPickedIdeaAuditReport($pickedReport);

            $this->article->temporal = $pickedReport
                ->getIdea()
                ->getIntent()
                ->getTemporal();

            $this->touchArticleQuietly();
            return null;
        }

        // 8) Pick author context for the winning idea when the client has authors.
        $authorContextProgress = $this->processAuthorContextSelection();
        if ($authorContextProgress !== true) {
            return $authorContextProgress;
        }

        return true;
    }

    /**
     * Resolves {@see IdeaStageData::getSelectedAuthorContext()} from client authors via the editor.
     *
     * @return ?true when selection is done or not needed; false on hard failure
     */
    protected function processAuthorContextSelection(): ?bool
    {
        $ideaData = $this->getIdeaStageData();

        if ($ideaData->hasSelectedAuthorContext()) {
            return true;
        }

        $pickedIdea = $ideaData->getPickedIdea();
        if (! $pickedIdea instanceof Idea) {
            return false;
        }

        $authorContexts = $this->collectClientAuthorContexts();
        if ($authorContexts === []) {
            return true;
        }

        $selected = $this->synthesizer()
            ->getEditor()
            ->determineAuthorContext($pickedIdea, $authorContexts);

        if (! $selected instanceof AuthorContext) {
            $selected = AuthorContext::fromArray($selected->toArray());
        }

        $ideaData->setSelectedAuthorContext($selected);

        // Set article's author_id
        if ($authorId = $selected->getIdentifier()
            and $authorId = explode('-', $authorId)
            and $authorId = end($authorId)
            and Author::query()->where('id', $authorId)->exists()
        ) {
            $this->article->author_id = $authorId;
        }

        $this->touchArticleQuietly();

        return true;
    }

    /**
     * @return list<AuthorContext>
     */
    protected function collectClientAuthorContexts(): array
    {
        return $this->client
            ->authors()
            ->get()
            ->map(static fn (Author $author): AuthorContext => $author->context)
            ->values()
            ->all();
    }

}
