<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\DOM\Article as DOMArticle;
use App\Contracts\Model\Article\StageData\RectificationStageData;
use App\Contracts\Model\Article\StageData\RectificationStageData\CriticRectificationState;
use App\Contracts\Synthesizer\Critic\Critic;
use App\Contracts\Synthesizer\Writer\Draft;
use App\Models\Article;
use App\Services\Synthesizer\Support\MaxRectificationRoundsResolver;

/**
 * RECTIFICATION stage: per-critic state map ordered by config "order"; one criticize
 * or rectify step per job tick.
 *
 * Critics are criticized in (order, purpose) sequence first, then pending
 * rectifications are processed.
 */
trait HandleRectificationStage
{
    /**
     * @return ?true when every critic in the pass is clean or max rounds reached;
     *               null while a sub-step is in-flight;
     *               false when draft/article DOM is missing.
     */
    protected function handleRectificationStage(): ?bool
    {
        $article = $this->article;
        if (! $article instanceof Article) {
            return false;
        }

        $dom = $this->resolveArticleDomForRectification();
        if (! $dom instanceof DOMArticle) {
            return false;
        }

        $critics = $this->synthesizer()->getCritics();
        if ($critics === []) {
            return true;
        }

        $stageData = $this->getStageData()->getRectificationStageData();
        $stageData->ensureCriticsInitialized($this->buildCriticEntriesForStage($critics));
        $this->touchArticleQuietly();

        // Priority 1: advance to the next unvisited critic.
        $next = $stageData->getNextPendingCritic();
        if ($next instanceof CriticRectificationState) {
            return $this->runCritic($dom, $stageData, $critics, $next->getPurpose());
        }

        // Priority 2: no critics left to criticize, process pending rectifications.
        $awaiting = $stageData->getCriticAwaitingRectification();
        if ($awaiting instanceof CriticRectificationState) {
            $rectifyProgress = $this->processArticleRectification($dom, $stageData, $awaiting);
            if ($rectifyProgress !== true) {
                return $rectifyProgress;
            }

            return $this->afterCriticStep($stageData);
        }

        return $this->finishPass($stageData);
    }

    // -------------------------------------------------------------------------
    // Per-step handlers
    // -------------------------------------------------------------------------

    protected function runCritic(
        DOMArticle $dom,
        RectificationStageData $stageData,
        array $critics,
        string $purpose,
    ): ?bool {
        $critic = $this->findCriticByPurpose($critics, $purpose);

        if (! $critic instanceof Critic) {
            $stageData->markCriticDone($purpose);
            $this->touchArticleQuietly();

            return $this->afterCriticStep($stageData);
        }

        $authorContext = $this->getStageData()->getDistilledAuthorContextForDraft();
        $criticisms = $critic->criticizeArticle(
            $dom,
            $authorContext,
            $this->buildSemanticContext(),
            $stageData->getRectifications(),
        );

        if ($criticisms === []) {
            $stageData->markCriticDone($purpose);
            $this->touchArticleQuietly();

            return $this->afterCriticStep($stageData);
        }

        $stageData->flagCriticAwaitingRectification($purpose, $criticisms);
        $this->touchArticleQuietly();

        return null;
    }

    protected function processArticleRectification(
        DOMArticle $dom,
        RectificationStageData $stageData,
        CriticRectificationState $criticState,
    ): ?bool {
        $criticisms = $criticState->getPendingCriticisms();
        $purpose = $criticState->getPurpose();

        if ($criticisms === []) {
            $stageData->markCriticDone($purpose);
            $this->touchArticleQuietly();

            return true;
        }

        $authorContext = $this->getStageData()->getDistilledAuthorContextForDraft();
        $rectified = $this->synthesizer()
            ->getWriter()
            ->rectifyArticle(
                $dom,
                $criticisms,
                $authorContext,
                $this->buildSemanticContext(),
            );

        $rectifiedArticle = $rectified->getArticle();
        if ($rectifiedArticle instanceof DOMArticle) {
            $this->persistRectifiedArticleDom($rectifiedArticle);
        }

        $stageData
            ->addRectificationsForCritic($purpose, $rectified->getRectifications())
            ->markCriticDone($purpose);
        $this->touchArticleQuietly();

        return null;
    }

    // -------------------------------------------------------------------------
    // Pass control
    // -------------------------------------------------------------------------

    /**
     * Called after any critic step completes (criticize or rectify).
     * Either continues to the next critic or closes the pass.
     */
    protected function afterCriticStep(RectificationStageData $stageData): ?bool
    {
        return $stageData->getNextPendingCritic() !== null
            || $stageData->getCriticAwaitingRectification() !== null
            ? null
            : $this->finishPass($stageData);
    }

    protected function finishPass(RectificationStageData $stageData): ?bool
    {
        if ($stageData->getMaxCriticRound() === 0) {
            return true;
        }

        if ($stageData->hasReachedMaxRounds($this->getMaxRectificationRounds())) {
            return true;
        }

        $stageData->advancePass()->resetForNextPass();
        $this->touchArticleQuietly();

        return null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param  list<Critic>  $critics
     */
    protected function findCriticByPurpose(array $critics, string $purpose): ?Critic
    {
        foreach ($critics as $critic) {
            if ($critic instanceof Critic && $critic->getPurpose() === $purpose) {
                return $critic;
            }
        }

        return null;
    }

    protected function resolveArticleDomForRectification(): ?DOMArticle
    {
        $draft = $this->getStageData()->getDraft();
        if ($draft instanceof Draft && $draft->getArticle() instanceof DOMArticle) {
            return $draft->getArticle();
        }

        $article = $this->article;
        if ($article?->article instanceof DOMArticle) {
            return $article->article;
        }

        return null;
    }

    protected function persistRectifiedArticleDom(DOMArticle $dom): void
    {
        $article = $this->article;
        if (! $article instanceof Article) {
            return;
        }

        $article->article = $dom;

        $draft = $this->getStageData()->getDraft();
        if ($draft instanceof Draft) {
            $draft->setArticle($dom);
            $this->getStageData()->setDraft($draft);
        }

        $this->touchArticleQuietly();
    }

    protected function getMaxRectificationRounds(): int
    {
        return MaxRectificationRoundsResolver::resolve();
    }

    /**
     * Build {purpose, order} entries from live critic objects.
     *
     * @param  list<Critic>  $critics
     * @return list<array{purpose: string, order: int}>
     */
    protected function buildCriticEntriesForStage(array $critics): array
    {
        $entries = [];
        foreach ($critics as $critic) {
            if (! $critic instanceof Critic) {
                continue;
            }

            $entries[] = [
                'purpose' => $critic->getPurpose(),
                'order' => $critic->getOrder(),
            ];
        }

        usort(
            $entries,
            static fn (array $a, array $b): int => $a['order'] <=> $b['order']
        );

        return $entries;
    }
}
