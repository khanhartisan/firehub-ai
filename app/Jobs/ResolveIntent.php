<?php

namespace App\Jobs;

use App\Contracts\IntentResolver\Intentable;
use App\Enums\Queue;
use App\Facades\IntentResolver;
use App\Facades\TextEmbedding;
use App\Models\Article;
use App\Models\Intent;
use App\Models\Page;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ResolveIntent implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    protected Lock $manualLock;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue(Queue::PAGE_SCRAPING->value);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$lock = $this->getManualLock()
            or !$lock->get()
        ) {
            if (env('APP_DEBUG')) {
                dump('Could not acquire lock for '.static::class);
            }
            return;
        }

        $startedAt = time();
        $limit = 1;
        $resolved = 0;

        while (true) {
            if (time() - $startedAt >= $this->timeout - 5) {
                break;
            }

            // Wait if we have any intents that are not embedded
            if (Intent::query()
                    ->where('is_embeddable', false)
                    ->exists()
                or Intent::query()
                    ->where('is_embeddable', true)
                    ->where('is_embedded', false)
                    ->exists()
            ) {
                $lock->release();
                return;
            }

            $resolved += $this->resolveArticleIntents($limit);
            $resolved += $this->resolvePageIntents($limit);
        }

        $lock->release();
        if ($resolved) {
            static::dispatch();
        }
    }

    protected function resolveArticleIntents(int $limit): int
    {
        $articles = Article::query()
            ->where('is_embedded', true)
            ->whereNull('intent_resolved_at')
            ->orderBy('updated_at')
            ->take($limit)
            ->get();

        $articles->each(function (Article $article) {
            $intentable = new Intentable()
                ->setContent($article->getTextForEmbedding());

            $intentableIntents = IntentResolver::resolve($intentable);
            foreach ($intentableIntents->getIntentableIntents() as $intentableIntent) {
                $intentData = $intentableIntent->getIntent();
                $intentVector = TextEmbedding::embed($intentData->getTitle()."\n".$intentData->getDescription());

                // TODO
            }
        });

        return $articles->count();
    }

    protected function resolvePageIntents(int $limit): int
    {
        $pages = Page::query()
            ->where('is_embedded', true)
            ->whereNull('intent_resolved_at')
            ->orderBy('updated_at')
            ->take($limit)
            ->get();

        // TODO:

        return $pages->count();
    }

    protected function getManualLock(): Lock
    {
        return $this->manualLock ??= Cache::lock(static::class);
    }
}
