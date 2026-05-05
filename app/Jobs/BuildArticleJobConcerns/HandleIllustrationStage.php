<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\DOM\Article as DOMArticle;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Filesystem\File;
use App\Contracts\Synthesizer\Author\IllustrationAnchor;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Models\Article;
use Illuminate\Support\Facades\Storage;

/**
 * ILLUSTRATION stage: resolves illustration contexts → generates one image per job run →
 * resolves anchors → weaves images into the article DOM.
 *
 * Each sub-step that calls an external service checkpoints (returns null) so the queue
 * can slice the work across multiple job ticks, matching the pattern used by IDEA and RESEARCH.
 */
trait HandleIllustrationStage
{
    /**
     * @return ?true when all illustrations are woven into the DOM;
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

        // 4. Weave illustrations into the DOM and persist — no external call.
        $this->applyIllustrationAnchors($dom);
        $article->article = $dom;
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

        $contexts = $stageData->getIllustrationContexts();
        if (empty($contexts)) {
            return false;
        }

        $nextContext = null;
        foreach ($contexts as $context) {
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

        $anchors = $this->synthesizer()->getAuthor()->getIllustrationAnchors($dom, $results);

        $stageData->setIllustrationAnchors($anchors);
        $this->touchArticleQuietly();

        return null;
    }

    /**
     * Inserts one <img> element per anchor into the DOM. Silently skips anchors whose target
     * element cannot be found (e.g. DOM was edited between stages).
     */
    protected function applyIllustrationAnchors(DOMArticle $dom): void
    {
        $stageData = $this->getStageData()->getIllustrationStageData();

        $resultMap = [];
        foreach ($stageData->getIllustrationResults() as $result) {
            $resultMap[$result->getIdentifier()] = $result;
        }

        $groupedAnchors = [];
        foreach ($stageData->getIllustrationAnchors() as $anchor) {
            if (! $anchor instanceof IllustrationAnchor) {
                continue;
            }
            $groupKey = $anchor->getElementIdentifier()."\0".($anchor->isAfter() ? '1' : '0');
            $groupedAnchors[$groupKey][] = $anchor;
        }

        foreach ($groupedAnchors as $anchors) {
            // Reverse so insertBefore/insertAfter keeps original order.
            foreach (array_reverse($anchors) as $anchor) {
                $result = $resultMap[$anchor->getIllustrationIdentifier()] ?? null;
                if (! $result) {
                    continue;
                }

                $imgElement = $this->buildImageElementFromResult($result);
                if (! $imgElement instanceof Element) {
                    continue;
                }

                $targetParent = $this->findDirectParentForElementId($dom, $anchor->getElementIdentifier());
                if (! $targetParent instanceof Element) {
                    continue;
                }

                try {
                    if ($anchor->isAfter()) {
                        $targetParent->insertAfter($anchor->getElementIdentifier(), $imgElement);
                    } else {
                        $targetParent->insertBefore($anchor->getElementIdentifier(), $imgElement);
                    }
                } catch (\Exception) {
                    // anchor element no longer present in DOM — skip
                }
            }
        }
    }

    protected function buildImageElementFromResult(IllustrationResult $result): ?Element
    {
        $files = $result->getFiles();
        $first = $files[0] ?? null;
        if (! $first instanceof File) {
            return null;
        }

        $path = trim($first->getPath());
        if ($path === '') {
            return null;
        }

        $src = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : Storage::url($path);

        return (new Element)
            ->setType(ElementType::IMG)
            ->setProp('src', $src)
            ->setProp('alt', $result->getIllustrationContext()?->getSubjectValue() ?: '');
    }

    protected function findDirectParentForElementId(Element $root, string $targetIdentifier): ?Element
    {
        foreach ($root->getChildren() as $child) {
            if (! $child instanceof Element) {
                continue;
            }

            if ($child->getIdentifier() === $targetIdentifier) {
                return $root;
            }

            $found = $this->findDirectParentForElementId($child, $targetIdentifier);
            if ($found instanceof Element) {
                return $found;
            }
        }

        return null;
    }
}
