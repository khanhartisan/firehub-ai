<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\DOM\Article as DOMArticle;
use App\Contracts\Model\Article\IllustrationData;
use App\Models\Article;

/**
 * ILLUSTRATION stage: resolves illustration contexts → generates one image per job run →
 * resolves anchors → persists illustration metadata on the article.
 *
 * Each sub-step that calls an external service checkpoints (returns null) so the queue
 * can slice the work across multiple job ticks, matching the pattern used by IDEA and RESEARCH.
 */
trait HandleIllustrationStage
{
    /**
     * @return ?true when illustration metadata is fully persisted;
     *               null while a sub-step is in-flight (checkpoint — same stage re-runs);
     *               false on invalid state (e.g. illustration stage reached before draft exists).
     */
    protected function handleIllustrationStage(): ?bool
    {
        $article = $this->article;
        if (! $article instanceof Article) {
            return false;
        }

        $draft = $this->getStageData()->getDraft();
        if (! $draft) {
            return false;
        }
        $dom = $draft->getArticle();
        if (! $dom) {
            return true;
        }

        // 1. Resolve illustration contexts (one external call; checkpoint after).
        $contextsProgress = $this->processIllustrationContextResolution($dom);
        if ($contextsProgress !== true) {
            return $contextsProgress;
        }

        // 2. Generate one illustration per job run until all contexts are covered.
        $generationProgress = $this->processIllustrationGeneration();
        if ($generationProgress !== true) {
            return $generationProgress;
        }

        // 3. Resolve DOM anchors (one external call; checkpoint after).
        $anchorProgress = $this->processIllustrationAnchorResolution($dom);
        if ($anchorProgress !== true) {
            return $anchorProgress;
        }

        // 4. Persist resolved illustration payload on the article.
        $stageData = $this->getStageData()->getIllustrationStageData();
        $article->illustration = (new IllustrationData)
            ->setIllustrationResults($stageData->getIllustrationResults())
            ->setIllustrationAnchors($stageData->getIllustrationAnchors());
        $this->touchArticleQuietly();

        return true;
    }

    /**
     * Calls the director to resolve illustration contexts, persists them, then checkpoints.
     * Skips cleanly when the DOM produces no illustratable content.
     */
    protected function processIllustrationContextResolution(DOMArticle $dom): ?bool
    {
        $stageData = $this->getStageData()->getIllustrationStageData();

        if (! empty($stageData->getIllustrationContexts())) {
            return true;
        }

        $contexts = $this->synthesizer()->getIllustrationDirector()->resolveIllustrationContexts($dom);
        if (empty($contexts)) {
            return false;
        }

        $stageData->setIllustrationContexts($contexts);
        $this->touchArticleQuietly();

        return null;
    }

    /**
     * Processes the next ungenerated context: directs it, picks an illustrator, generates the
     * image, persists the result, and checkpoints. Returns true when all contexts are covered.
     */
    protected function processIllustrationGeneration(): ?bool
    {
        $stageData = $this->getStageData()->getIllustrationStageData();

        $tasks = $stageData->getIllustrationTasks();
        if (empty($tasks)) {
            return false;
        }

        $nextContext = null;
        foreach ($tasks as $task) {
            $context = $task->getIllustrationContext();
            if (! $context) {
                continue;
            }

            if (! $stageData->hasIllustrationResultForContextIdentifier($context->getIdentifier())) {
                $nextContext = $context;
                break;
            }
        }

        if (! $nextContext) {
            return true;
        }

        $director = $this->synthesizer()->getIllustrationDirector();
        $contextIdentifier = $nextContext->getIdentifier();
        $direction = $stageData->getIllustrationDirectionByContextIdentifier($contextIdentifier);

        // Checkpoint 1: resolve direction only (one AI call max).
        if (! $direction) {
            $direction = $director->direct($nextContext);
            if (! $direction) {
                return false;
            }

            $stageData->setIllustrationDirectionForContextIdentifier($contextIdentifier, $direction);
            $this->touchArticleQuietly();

            return null;
        }

        $illustrator = $director->determineIllustrator($nextContext, $direction, $this->synthesizer()->getIllustrators());
        if (! $illustrator) {
            return false;
        }

        // Checkpoint 2: generate image only (one AI call max).
        $result = $illustrator->generate($nextContext, $direction);
        if (! $result) {
            return false;
        }
        $stageData->addIllustrationResult($result);

        $this->touchArticleQuietly();

        return null;
    }

    /**
     * Asks the author to map illustration results to DOM anchor points, persists them, then
     * checkpoints. Skips when there are no results to anchor.
     */
    protected function processIllustrationAnchorResolution(DOMArticle $dom): ?bool
    {
        $stageData = $this->getStageData()->getIllustrationStageData();

        if ($stageData->isIllustrationAnchorsResolved()) {
            return true;
        }

        $results = $stageData->getIllustrationResults();
        if (empty($results)) {
            return true;
        }

        $anchors = $this->synthesizer()->getWriter()->getIllustrationAnchors($dom, $results);

        $stageData->setIllustrationAnchors($anchors);
        $this->touchArticleQuietly();

        return null;
    }
}
