<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns\HandleIdeaStageBrainstorm;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns\HandleIdeaStageContext;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns\HandleIdeaStageIntentMerging;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns\HandleIdeaStageSuggestionCollection;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStageConcerns\HandleIdeaStageUniquenessAndAudit;
use App\Models\Article;
use App\Utils\Str;

/**
 * IDEA stage: suggestions → weighted top picks → brainstorm → merge intents → uniqueness → audit → pick one idea.
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

        // Idea brainstorm context, appending the latest articles
        $ideaBrainstormContext = clone $context;
        if ($latestArticles = $this->client
            ->articles()
            ->take(1000)
            ->orderByDesc('id')
            ->get()
            ->filter(function (Article $article) {
                return !! $article->title;
            })
            and $latestArticles->count()
        ) {
            $ideaBrainstormContext->set('latest_article_titles', 'Latest article titles for ideation context.', $latestArticles
                ->map(function (Article $article) {
                    return Str::limit($article->title, 160);
                })->values()->toArray());
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

        // Resume path: we already persisted the final choice on a previous run.
        if ($ideaData->getPickedIdeaAuditReport() instanceof IdeaAuditReport) {
            return true;
        }

        // 7) Pick one winning idea (limit 1).
        $pickedList = $this->getIdeaForgeService()->getIdeaPicker()->pick($ideaAuditReports, $context, 1) ?? [];
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

        return true;
    }

    protected function buildSemanticContext(): ?SemanticContext
    {
        $context = new SemanticContext;
        $hasAny = false;

        if ($this->client->context) {
            $clientContextPayload = $this->client->context->toArray();
            $hasClientContextValue = false;
            foreach ($clientContextPayload as $entry) {
                if (is_array($entry)
                    && array_key_exists('value', $entry)
                    && $this->contextPayloadHasContent($entry['value'])
                ) {
                    $hasClientContextValue = true;
                    break;
                }
            }

            if ($hasClientContextValue) {
                $context->set('client_context', 'Client context DTO payload.', $clientContextPayload);
                $hasAny = true;
            }
        }

        $articleContext = trim((string) ($this->article?->context ?? ''));
        if ($articleContext !== '') {
            $context->set('article_context', 'Article-specific context provided by the user.', $articleContext);
            $hasAny = true;
        }

        return $hasAny ? $context : null;
    }

    protected function contextPayloadHasContent(mixed $payload): bool
    {
        if ($payload === null) {
            return false;
        }

        if (is_string($payload)) {
            return trim($payload) !== '';
        }

        if (is_int($payload) || is_float($payload) || is_bool($payload)) {
            return true;
        }

        if (! is_array($payload)) {
            return false;
        }

        foreach ($payload as $value) {
            if ($this->contextPayloadHasContent($value)) {
                return true;
            }
        }

        return false;
    }
}
