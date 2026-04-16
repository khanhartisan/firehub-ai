<?php

namespace App\Jobs;

use App\Enums\ArticleStage;
use App\Enums\ArticleStatus;
use App\Enums\Queue;
use App\Jobs\BuildArticleJobConcerns\HandleIdeaStage;
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
        if (!$article = $this->article) {
            return;
        }

        if ($article->status !== ArticleStatus::UNREADY) {
            return;
        }

        if (!$lock = $this->getManualLock() or !$lock->get()) {
            return;
        }

        $article->stage = $article->stage ?: ArticleStage::IDEA;

        // Idea stage
        if ($article->stage === ArticleStage::IDEA) {
            $stageResult = $this->handleIdeaStage();

            // Return null mean processing -> continue dispatching the job
            if (is_null($stageResult)) {
                $lock->release();
                static::dispatch($this->client, $this->articleId);
                return;
            }

            // False means failed
            if (!$stageResult) {

            }

            // Move to the next stage
        }

        $lock->release();
    }

    protected function getManualLock(): Lock
    {
        return $this->manualLock ??= Cache::lock(sha1($this->uniqueId()), $this->uniqueFor);
    }
}
