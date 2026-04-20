<?php

namespace App\Jobs;

use App\Contracts\IntentResolver\Intentable;
use App\Contracts\IntentResolver\IntentableIntent;
use App\Contracts\VectorDB\SearchOptions;
use App\Enums\Queue;
use App\Facades\IntentResolver;
use App\Facades\TextEmbedding;
use App\Facades\VectorDB;
use App\Models\Article;
use App\Models\ArticleIntent;
use App\Models\Intent;
use App\Models\IntentKeyword;
use App\Models\IntentPage;
use App\Models\Keyword;
use App\Models\Page;
use App\Utils\Str;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ResolveIntentJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    protected Lock $manualLock;

    public int $timeout = 300;

    public int $uniqueFor = 120;

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
                dump('Skipped intent resolution job: lock not acquired (another run may be in progress).', [
                    'job' => static::class,
                ]);
            }

            return;
        }

        $startedAt = time();
        $resolved = 0;

        try {

            while (true) {
                if (time() - $startedAt >= 60) {
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
                    if (env('APP_DEBUG')) {
                        dump('Found un-embedded intent, return and wait...');
                    }

                    $lock->release();
                    return;
                }

                $resolvedKeywordIntents = $this->resolveKeywordIntents(10);
                $resolvedArticleIntents = $this->resolveArticleIntents(1);
                $resolvedPageIntents = $this->resolvePageIntents(1);
                if (!$resolvedKeywordIntents
                    and !$resolvedArticleIntents
                    and !$resolvedPageIntents
                ) {
                    break;
                }

                $resolved += $resolvedKeywordIntents + $resolvedArticleIntents + $resolvedPageIntents;
            }

        } finally {
            $lock->release();
            if ($resolved) {
                ResolveIntentJob::dispatch();
            }
        }
    }

    protected function resolveKeywordIntents(int $limit): int
    {
        $keywords = Keyword::query()
            ->whereNull('intent_resolved_at')
            ->orderBy('updated_at')
            ->take($limit)
            ->get();

        if ($keywords->isEmpty()) {
            return 0;
        }

        $listIntentKeywords = IntentResolver::inferFromKeywords($keywords->pluck('keyword')->values()->toArray());

        foreach ($listIntentKeywords as $intentKeywords) {
            $intentModel = $this->getIntentModelByIntentData($intentKeywords->getIntent());

            foreach ($intentKeywords->getIntentKeywords() as $intentKeyword) {
                $keywordModel = $keywords
                    ->firstWhere(
                        'keyword',
                        Str::sanitizeKeyword($intentKeyword->getKeyword())
                    );

                if (!$keywordModel) {
                    continue;
                }

                $intentKeywordModel = IntentKeyword::query()->firstOrNew([
                    'keyword_id' => $keywordModel->id,
                    'intent_id' => $intentModel->id
                ]);
                $intentKeywordModel->relevance = $intentKeyword->getRelevance();
                DB::transaction(fn () => $intentKeywordModel->save());
            }
        }

        Keyword::query()
            ->whereIn('id', $keywords->pluck('id'))
            ->update([
                'intent_resolved_at' => now(),
                'updated_at' => now(),
            ]);

        return $keywords->count();
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
            $articleIntentIds = [];
            foreach ($intentableIntents->getIntentableIntents() as $intentableIntent) {
                $intentModel = $this->getIntentModelByIntentableIntent($intentableIntent);

                $articleIntent = ArticleIntent::query()->firstOrCreate([
                    'article_id' => $article->id,
                    'intent_id' => $intentModel->id,
                ]);
                $articleIntent->relevance = $intentableIntent->getRelevance();
                $articleIntent->save();

                $articleIntentIds[] = $articleIntent->id;
            }

            if ($articleIntentIds) {
                ArticleIntent::query()
                    ->where('article_id', $article->id)
                    ->whereNotIn('id', $articleIntentIds)
                    ->get()->each->delete();
            }

            $article->intent_resolved_at = now();
            $article->save();
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

        $pages->each(function (Page $page) {
            $intentable = new Intentable()
                ->setContent($page->getTextForEmbedding());

            $intentableIntents = IntentResolver::resolve($intentable);
            $intentPageIds = [];
            foreach ($intentableIntents->getIntentableIntents() as $intentableIntent) {
                $intentModel = $this->getIntentModelByIntentableIntent($intentableIntent);

                $intentPage = IntentPage::query()->firstOrNew([
                    'intent_id' => $intentModel->id,
                    'page_id' => $page->id,
                ]);
                $intentPage->relevance = $intentableIntent->getRelevance();
                $intentPage->save();

                $intentPageIds[] = $intentPage->id;
            }

            if ($intentPageIds) {
                IntentPage::query()
                    ->where('page_id', $page->id)
                    ->whereNotIn('id', $intentPageIds)
                    ->get()->each->delete();
            }

            $page->intent_resolved_at = now();
            $page->save();
        });

        return $pages->count();
    }

    protected function getIntentModelByIntentableIntent(IntentableIntent $intentableIntent): Intent
    {
        return $this->getIntentModelByIntentData($intentableIntent->getIntent());
    }

    protected function getIntentModelByIntentData(\App\Contracts\IntentResolver\Intent $intentData): Intent
    {
        $intentVector = TextEmbedding::embed($intentData->getTitle()."\n".$intentData->getDescription());

        $similarIntents = VectorDB::search(
            new Intent()->getVectorIndex(),
            $intentVector,
            new SearchOptions(
                10,
                [
                    'language' => $intentData->getLanguage()->value
                ],
                0.85
            )
        );

        if (env('APP_DEBUG') and app()->runningInConsole()) {
            dump('Found '.count($similarIntents).' similar intents');
        }

        // Check merging
        foreach ($similarIntents as $similarIntent) {
            if (!$intentModel = Intent::query()->find($similarIntent->record->id)) {
                continue;
            }

            $similarIntentData = new \App\Contracts\IntentResolver\Intent()
                ->setTitle($intentModel->title)
                ->setDescription($intentModel->description)
                ->setLanguage($intentModel->language);

            $mergedIntentData = IntentResolver::mergeIntents(
                $intentData,
                $similarIntentData
            );

            if ($mergedIntentData) {
                if (env('APP_DEBUG') and app()->runningInConsole()) {
                dump('Merging intents...');
                }

                $intentModel->temporal = $mergedIntentData->getTemporal();
                $intentModel->title = $mergedIntentData->getTitle();
                $intentModel->description = $mergedIntentData->getDescription();
                $intentModel->types = $mergedIntentData->getTypes();
                DB::transaction(fn () => $intentModel->save());

                // Update new vector
                if (env('APP_DEBUG') and app()->runningInConsole()) {
                    dump('Update merged intent embedding...');
                }
                $newIntentVector = TextEmbedding::embed($intentModel->getTextForEmbedding());
                DB::transaction(fn () => $intentModel->setEmbedding($newIntentVector));

                return $intentModel;
            }
        }

        $newIntentModel = new Intent();
        $newIntentModel->temporal = $intentData->getTemporal();
        $newIntentModel->title = $intentData->getTitle();
        $newIntentModel->description = $intentData->getDescription();
        $newIntentModel->language = $intentData->getLanguage();
        $newIntentModel->types = $intentData->getTypes();
        DB::transaction(fn () => $newIntentModel->setEmbedding($intentVector));

        return $newIntentModel;
    }

    protected function getManualLock(): Lock
    {
        return $this->manualLock ??= Cache::lock(static::class, $this->uniqueFor);
    }
}
