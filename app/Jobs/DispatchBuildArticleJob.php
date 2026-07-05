<?php

namespace App\Jobs;

use App\Enums\ArticleStatus;
use App\Enums\Queue;
use App\Models\Article;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchBuildArticleJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 60;

    public int $uniqueFor = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue(Queue::SCHEDULER->value);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = time();
        while (true) {
            if (time() - $startTime < $this->timeout - 5) {
                return;
            }

            if (!Queue::ARTICLE_BUILDING->canDispatch()) {
                return;
            }

            if (!$article = Article::query()
                ->where('status', ArticleStatus::PROCESSING)
                ->where('updated_at', '<', now()->subminutes(5))
                ->orderBy('updated_at')
                ->first()
            ) {
                return;
            }

            $article->touchQuietly();

            // TODO: Continue
        }
    }
}
