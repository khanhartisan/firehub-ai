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

trait HandleIdeaStage
{
    use HandleIdeaStageContext;
    use HandleIdeaStageSuggestionCollection;
    use HandleIdeaStageBrainstorm;
    use HandleIdeaStageIntentMerging;
    use HandleIdeaStageUniquenessAndAudit;

    /**
     * @throws \Exception
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

        // Get latest posts
        $latestArticles = $this->client
            ->articles()
            ->take(1000)
            ->orderByDesc('id')
            ->get()
            ->filter(function (Article $article) {
                return !! $article->title;
            });

        // Idea brainstorm context
        $ideaBrainstormContext = $context
        ."\n---\n Below is the list of the latest articles:\n"
        .$latestArticles
            ->map(function (Article $article) {
                return Str::limit($article->title, 160);
            })->join("\n- ");

        // 1) Collect advisor suggestions.
        // Each costly step checkpoints and exits for re-execution.
        $suggestionProgress = $this->processSuggestionCollection($ideaBrainstormContext);
        if ($suggestionProgress !== true) {
            return $suggestionProgress;
        }

        // 2) Pick one highest-confidence temporal + intent suggestion globally.
        $topSelection = $this->processTopSuggestionSelection();
        if ($topSelection !== true) {
            return $topSelection;
        }

        // 3) Brainstorm per advisor using the selected temporal + intent pair.
        $brainstormProgress = $this->processBrainstormCollection($ideaBrainstormContext);
        if ($brainstormProgress !== true) {
            return $brainstormProgress;
        }

        // 4) Merge similar intents
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
        $ideaAuditReports = $ideaData->getAuditReports();

        if ($ideaData->getPickedReport() instanceof IdeaAuditReport) {
            return true;
        }

        // 7) Persist picker output first, then finalize picked report on next run.
        if (! $ideaData->hasPickedReports()) {
            $pickedReports = $this
                ->getIdeaForgeService()
                ->getIdeaPicker()
                ->pick($ideaAuditReports, $context, 1)
                ?? [];
            if ($pickedReports === []) {
                return false;
            }

            $ideaData->setPickedReports($pickedReports);
            $this->touchArticleQuietly();

            return null;
        }

        $pickedReports = $ideaData->getPickedReports();
        if (! $pickedReports) {
            return false;
        }

        $pickedReport = collect($pickedReports)
            ->first(fn ($report) => $report instanceof IdeaAuditReport);
        if (! $pickedReport instanceof IdeaAuditReport) {
            return false;
        }

        $ideaData->setPickedReport($pickedReport);
        $this->touchArticleQuietly();
        $this->article->temporal = $pickedReport
            ->getIdea()
            ->getIntent()
            ->getTemporal();
        $this->touchArticleQuietly();

        return true;
    }
}
