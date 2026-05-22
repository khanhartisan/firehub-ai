<?php

namespace App\Jobs;

use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Enums\Queue;
use App\Jobs\BuildArticleJobConcerns\HandleBriefStage;
use App\Jobs\BuildArticleJobConcerns\HandleDraftStage;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStage;
use App\Jobs\BuildArticleJobConcerns\HandleIllustrationStage;
use App\Jobs\BuildArticleJobConcerns\HandleOutlineStage;
use App\Jobs\BuildArticleJobConcerns\HandleResearchStage;
use App\Jobs\BuildArticleJobConcerns\InteractsWithArticleStageData;
use App\Jobs\BuildArticleJobConcerns\InteractsWithSemanticContext;
use App\Jobs\BuildArticleJobConcerns\InteractsWithSynthesizer;
use App\Jobs\Concerns\HasManualLock;
use App\Models\Article;
use App\Models\Client;
use Exception;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Queued article build: one stage worth of work per job execution, then self-dispatch.
 *
 * Flow:
 * - Only runs when the article status is UNREADY. Uses a cache lock (uniqueId) so the same
 *   article is not processed concurrently.
 * - Marks the row PROCESSING, calls the handler for the current {@see ArticleStage}, then
 *   interprets the result (see below).
 * - Stage implementations live in traits (HandleIdeaStage, HandleBriefStage, …). They persist
 *   progress by mutating the {@see StageData} tree from {@see InteractsWithArticleStageData::getStageData()}
 *   (same object as {@see Article::$stage_data}), then {@see static::touchArticleQuietly()}.
 *   Shared accessors: {@see InteractsWithArticleStageData}, {@see InteractsWithSynthesizer}.
 *
 * Stage handler return value (and thus {@see runCurrentStage()}):
 * - true  — this stage is finished; the job advances stage (or marks READY if already FINAL).
 * - false — unrecoverable failure; article is marked FAILED.
 * - null  — not done yet (e.g. checkpoint after an AI call); job re-dispatches without advancing stage.
 *
 * After a successful stage (true), if the stage is not FINAL, the job bumps to the next stage,
 * sets PENDING, saves, and dispatches itself again so the next run executes the next stage.
 */
class BuildArticleJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;
    use InteractsWithArticleStageData;
    use InteractsWithSemanticContext;
    use InteractsWithSynthesizer;
    use HasManualLock;
    use HandleIdeaStage;
    use HandleResearchStage;
    use HandleBriefStage;
    use HandleDraftStage;
    use HandleOutlineStage;
    use HandleIllustrationStage;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public bool $deleteWhenMissingModels = true;

    protected Client $client;

    protected ?Article $article;

    protected int $reDispatchDelay = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(Client $client, protected string $articleId)
    {
        $this->client = $client;

        /** @var ?Article $article */
        $this->article = $client->articles()->find($articleId);

        $this->onQueue(Queue::ARTICLE_BUILDING->value);
    }

    public function uniqueId(): string
    {
        return static::class.'@'.$this->articleId;
    }

    /**
     * Run at most one stage transition per invocation; rely on queue for the rest.
     */
    public function handle(): void
    {
        // Job was queued for a deleted article (or race): nothing to do.
        if (! $article = $this->article) {
            return;
        }

        // Do not rebuild articles that already finished or were stopped elsewhere.
        if ($article->status !== ArticleStatus::UNREADY) {
            return;
        }

        // Same article must not run two builds at once (queue can deliver duplicates).
        if (! $lock = $this->getManualLock() or ! $lock->get()) {
            return;
        }

        try {
            // Hard cap on thrown errors / retries (separate from stage checkpoints).
            if ($article->attempts >= $this->getMaxAttempts()) {
                $this->markArticleFailed(ArticleStatus::FAILED);
                return;
            }

            $article->stage = $article->stage ?: ArticleStage::IDEA;
            $article->stage_status = ArticleStageStatus::PROCESSING;
            $article->save();

            $stageResult = $this->runCurrentStage();

            // Stage asked to pause (e.g. IDEA checkpoint): re-queue same stage, do not advance.
            if (is_null($stageResult)) {
                $this->reDispatch();
                return;
            }

            // Stage reported hard failure (e.g. no ideas, picker empty).
            if (! $stageResult) {
                $this->markArticleFailed(ArticleStatus::FAILED);
                return;
            }

            // After DRAFT succeeds, stage is still DRAFT in memory here; next lines bump to FINAL and re-dispatch.
            // On the following run, stage is FINAL, handler is a no-op true, and we mark READY below.
            if ($article->stage === ArticleStage::FINAL) {
                $this->markArticleReady();
                return;
            }

            // Finished this stage in one go: move pipeline forward and queue the next stage.
            $article->stage = $this->getNextStage($article->stage);
            $article->stage_status = ArticleStageStatus::PENDING;
            $article->save();

            $this->reDispatch();
        } catch (\Throwable $e) {
            $this->recordError($e);

            // Too many failures: stop retrying and mark terminal error.
            if (($article->attempts ?? 0) >= $this->getMaxAttempts()) {
                $this->markArticleFailed(ArticleStatus::ERROR);
                return;
            }

            // Leave article buildable and retry later.
            $article->status = ArticleStatus::UNREADY;
            $article->stage_status = ArticleStageStatus::PENDING;
            $article->save();

            $this->reDispatch();
        }
        finally {
            $lock->release();
        }
    }

    /**
     * Delegates to the trait that matches {@see Article::$stage}. FINAL is a no-op success,
     * so the outer handle() path can mark the article READY.
     * @throws Exception
     */
    protected function runCurrentStage(): ?bool
    {
        $article = $this->article;
        if (! $article) {
            return false;
        }

        return match ($article->stage) {
            ArticleStage::IDEA => $this->handleIdeaStage(),
            ArticleStage::RESEARCH => $this->handleResearchStage(),
            ArticleStage::BRIEF => $this->handleBriefStage(),
            ArticleStage::OUTLINE => $this->handleOutlineStage(),
            ArticleStage::DRAFT => $this->handleDraftStage(),
            ArticleStage::ILLUSTRATION => $this->handleIllustrationStage(),
            ArticleStage::FINAL => true,
        };
    }

    protected function getNextStage(ArticleStage $currentStage): ArticleStage
    {
        return match ($currentStage) {
            ArticleStage::IDEA => ArticleStage::RESEARCH,
            ArticleStage::RESEARCH => ArticleStage::BRIEF,
            ArticleStage::BRIEF => ArticleStage::OUTLINE,
            ArticleStage::OUTLINE => ArticleStage::DRAFT,
            ArticleStage::DRAFT => ArticleStage::ILLUSTRATION,
            ArticleStage::ILLUSTRATION, ArticleStage::FINAL => ArticleStage::FINAL,
        };
    }

    protected function markArticleFailed(ArticleStatus $status): void
    {
        if (! $this->article) {
            return;
        }

        $this->article->status = $status;
        $this->article->stage_status = ArticleStageStatus::REJECTED;
        $this->article->save();
    }

    protected function markArticleReady(): void
    {
        if (! $this->article) {
            return;
        }

        $this->article->status = ArticleStatus::READY;
        $this->article->stage_status = ArticleStageStatus::APPROVED;
        $this->article->attempts = 0;
        $this->article->save();
    }

    protected function getMaxAttempts(): int
    {
        return max(1, (int) config('queue.max_article_build_attempts', 5));
    }

    protected function recordError(\Throwable $e): void
    {
        if (! $this->article) {
            return;
        }

        $logs = (string) ($this->article->error_logs ?? '');
        $logs .= "\n---\n".$e->getMessage()."\n---\n".$e->getTraceAsString();

        // Cap size so the DB column and logs stay bounded.
        $maxLength = 20 * 1024;
        if (strlen($logs) > $maxLength) {
            $prefix = '...trimmed...'."\n";
            $logs = $prefix.substr($logs, -($maxLength - strlen($prefix)));
        }

        // Count this failure toward max_article_build_attempts.
        $this->article->attempts = (int) ($this->article->attempts ?? 0) + 1;
        $this->article->error_logs = $logs;
        $this->article->saveQuietly();
    }

    /**
     * Persists {@see Article::$stage_data} without touching status/stage columns.
     * Callers mutate the DTO graph from {@see InteractsWithArticleStageData::getStageData()} (no reassignment).
     */
    protected function touchArticleQuietly(): void
    {
        if (! $this->article) {
            return;
        }

        $this->article->updated_at = now();
        $this->article->saveQuietly();
    }

    /**
     * @throws Exception
     */
    protected function reDispatch(): void
    {
        $this->getManualLock()->release();
        static::dispatch($this->client, $this->articleId)->delay($this->reDispatchDelay);
    }
}
