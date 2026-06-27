<?php

namespace App\Jobs\BuildArticleJobConcerns;

use App\Contracts\DOM\Article as DOMArticle;
use App\Contracts\Filesystem\File as FilesystemFile;
use App\Contracts\Model\Article\IllustrationData;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Enums\ScrapingStatus;
use App\Models\Article;
use App\Models\File;
use Illuminate\Support\Facades\Storage;

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
        $this->assignThumbnailFromIllustrationResults($stageData->getIllustrationResults());
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
        $this->persistIllustrationFilesForResult($result);

        $this->touchArticleQuietly();

        return null;
    }

    /**
     * Maps generated illustration storage paths to {@see File} records and attaches them to the article.
     */
    protected function persistIllustrationFilesForResult(IllustrationResult $result): void
    {
        $article = $this->article;
        if (! $article instanceof Article) {
            return;
        }

        $description = $result->getIllustrationContext()?->getSubjectValue();

        foreach ($result->getFiles() as $filesystemFile) {
            if (! $filesystemFile instanceof FilesystemFile) {
                continue;
            }

            $path = trim($filesystemFile->getPath());
            if ($path === '') {
                continue;
            }

            $file = $this->resolveOrCreateFileFromStoragePath($path, $description);
            $article->attachFile($file);
        }
    }

    protected function resolveOrCreateFileFromStoragePath(string $path, ?string $description = null): File
    {
        if ($file = File::query()->where('path', $path)->first()) {
            return $file;
        }

        $url = 'storage://'.$path;
        $urlHash = File::getUrlHash($url);

        if ($file = File::query()->where('url_hash', $urlHash)->first()) {
            if (! is_string($file->path) || $file->path === '') {
                $file->path = $path;
                $file->saveQuietly();
            }

            return $file;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: null;
        $mimeType = Storage::exists($path) ? (Storage::mimeType($path) ?: null) : null;

        return File::query()->create([
            'url' => $url,
            'path' => $path,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => Storage::exists($path) ? Storage::size($path) : null,
            'scraping_status' => ScrapingStatus::SUCCESS,
            'scraped_at' => now(),
            'description' => $description,
        ]);
    }

    /**
     * Sets {@see Article::$thumbnail_file_id} from the first generated illustration file when unset.
     *
     * @param  IllustrationResult[]  $results
     */
    protected function assignThumbnailFromIllustrationResults(array $results): void
    {
        $article = $this->article;
        if (! $article instanceof Article || $article->thumbnail_file_id) {
            return;
        }

        foreach ($results as $result) {
            if (! $result instanceof IllustrationResult) {
                continue;
            }

            foreach ($result->getFiles() as $filesystemFile) {
                if (! $filesystemFile instanceof FilesystemFile) {
                    continue;
                }

                $path = trim($filesystemFile->getPath());
                if ($path === '') {
                    continue;
                }

                $file = File::query()->where('path', $path)->first();
                if ($file instanceof File) {
                    $article->thumbnail_file_id = $file->id;

                    return;
                }
            }
        }
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
