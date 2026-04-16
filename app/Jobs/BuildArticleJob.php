<?php

namespace App\Jobs;

use App\Contracts\Model\Article\StageData;
use App\Enums\ArticleStage;
use App\Enums\ArticleStageStatus;
use App\Enums\ArticleStatus;
use App\Enums\Queue;
use App\Jobs\BuildArticleJobConcerns\HandleBriefStage;
use App\Jobs\BuildArticleJobConcerns\HandleDraftStage;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStage;
use App\Jobs\BuildArticleJobConcerns\HandleOutlineStage;
use App\Models\Article;
use App\Models\Client;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class BuildArticleJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;
    use HandleIdeaStage;
    use HandleBriefStage;
    use HandleDraftStage;
    use HandleOutlineStage;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public bool $deleteWhenMissingModels = true;

    protected Client $client;

    protected ?Article $article;

    protected Lock $manualLock;

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
     * Execute the job.
     */
    public function handle(): void
    {
        if (! $article = $this->article) {
            return;
        }

        if ($article->status !== ArticleStatus::UNREADY) {
            return;
        }

        if (! $lock = $this->getManualLock() or ! $lock->get()) {
            return;
        }

        try {
            if ($article->attempts >= $this->getMaxAttempts()) {
                $this->markArticleFailed(ArticleStatus::FAILED);
                return;
            }

            $article->stage = $article->stage ?: ArticleStage::IDEA;
            $article->stage_status = ArticleStageStatus::PROCESSING;
            $article->save();

            $stageResult = $this->runCurrentStage();

            // Null means stage is still processing.
            if (is_null($stageResult)) {
                static::dispatch($this->client, $this->articleId);
                return;
            }

            // False means failed.
            if (! $stageResult) {
                $this->markArticleFailed(ArticleStatus::FAILED);
                return;
            }

            if ($article->stage === ArticleStage::FINAL) {
                $this->markArticleReady();
                return;
            }

            $article->stage = $this->getNextStage($article->stage);
            $article->stage_status = ArticleStageStatus::PENDING;
            $article->save();

            static::dispatch($this->client, $this->articleId);
        } catch (\Throwable $e) {
            $this->recordError($e);

            if (($article->attempts ?? 0) >= $this->getMaxAttempts()) {
                $this->markArticleFailed(ArticleStatus::ERROR);
                return;
            }

            $article->status = ArticleStatus::UNREADY;
            $article->stage_status = ArticleStageStatus::PENDING;
            $article->save();
            static::dispatch($this->client, $this->articleId);
        }
        finally {
            $lock->release();
        }
    }

    protected function getManualLock(): Lock
    {
        return $this->manualLock ??= Cache::lock(sha1($this->uniqueId()), $this->uniqueFor);
    }

    protected function runCurrentStage(): ?bool
    {
        $article = $this->article;
        if (! $article) {
            return false;
        }

        return match ($article->stage) {
            ArticleStage::IDEA => $this->handleIdeaStage(),
            ArticleStage::BRIEF => $this->handleBriefStage(),
            ArticleStage::OUTLINE => $this->handleOutlineStage(),
            ArticleStage::DRAFT => $this->handleDraftStage(),
            ArticleStage::FINAL => true,
        };
    }

    protected function getNextStage(ArticleStage $currentStage): ArticleStage
    {
        return match ($currentStage) {
            ArticleStage::IDEA => ArticleStage::BRIEF,
            ArticleStage::BRIEF => ArticleStage::OUTLINE,
            ArticleStage::OUTLINE => ArticleStage::DRAFT,
            ArticleStage::DRAFT, ArticleStage::FINAL => ArticleStage::FINAL,
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

        // Keep no more than 10KB of error logs.
        $maxLength = 10 * 1024;
        if (strlen($logs) > $maxLength) {
            $prefix = '...trimmed...'."\n";
            $logs = $prefix.substr($logs, -($maxLength - strlen($prefix)));
        }

        $this->article->attempts = (int) ($this->article->attempts ?? 0) + 1;
        $this->article->error_logs = $logs;
        $this->article->saveQuietly();
    }

    protected function touchArticleQuietly(?StageData $stageData = null): void
    {
        if (! $this->article) {
            return;
        }

        $this->article->stage_data = $stageData instanceof StageData
            ? $stageData
            : ($this->article->stage_data instanceof StageData ? $this->article->stage_data : StageData::fromArray([]));

        $this->article->updated_at = now();
        $this->article->saveQuietly();
    }
}
