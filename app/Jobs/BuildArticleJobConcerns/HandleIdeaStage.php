<?php

namespace App\Jobs\BuildArticleJobConcerns;

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

        // Common context
        $clientContext = trim($this->client->context);
        $articleContext = trim($this->article->context);
        $context = $clientContext."\n---\n".$articleContext;
        if (! $clientContext && ! $articleContext) {
            return false;
        }

        // Idea brainstorm context, appending the latest articles
        $ideaBrainstormContext = $context;
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
            $ideaBrainstormContext .= "\n---\n Below is the list of the latest articles:\n"
            .$latestArticles
                ->map(function (Article $article) {
                    return Str::limit($article->title, 160);
                })->join("\n- ");
        } else {
            $ideaBrainstormContext .= "\n---\n This will be the first article.";
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
}
