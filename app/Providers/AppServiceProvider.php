<?php

namespace App\Providers;

use App\Contracts\FileVision\FileVision;
use App\Contracts\IntentResolver\IntentResolver;
use App\Contracts\OpenAI\OpenAIClient;
use App\Contracts\PageClassifier\Classifier;
use App\Contracts\PageParser\Parser;
use App\Contracts\ScrapePolicyEngine\ScrapePolicyEngine;
use App\Contracts\Scraper\Scraper;
use App\Contracts\SearchEngine\SearchEngine;
use App\Contracts\TextEmbedding\TextEmbedding as TextEmbeddingContract;
use App\Contracts\VectorDB\VectorDB;
use App\Contracts\VerticalResolver\VerticalResolver;
use App\Models\Article;
use App\Models\ArticleIntent;
use App\Models\Client;
use App\Models\File;
use App\Models\Fileable;
use App\Models\Intent;
use App\Models\IntentKeyword;
use App\Models\IntentPage;
use App\Models\Keyword;
use App\Models\Model;
use App\Models\Page;
use App\Models\PageCount;
use App\Models\PageRelation;
use App\Models\PageTag;
use App\Models\PageVertical;
use App\Models\Snapshot;
use App\Models\Source;
use App\Models\SourceVertical;
use App\Models\Tag;
use App\Models\User;
use App\Models\Vertical;
use App\Services\FileVision\FileVisionManager;
use App\Services\IntentResolver\IntentResolverManager;
use App\Services\OpenAI\OpenAIManager;
use App\Services\PageClassifier\PageClassifierManager;
use App\Services\PageParser\PageParserManager;
use App\Services\ScrapePolicyEngine\ScrapePolicyEngineManager;
use App\Services\Scraper\ScraperManager;
use App\Services\SearchEngine\SearchEngineManager;
use App\Services\TextEmbedding\TextEmbeddingManager;
use App\Services\VectorDB\VectorDBManager;
use App\Services\VerticalResolver\VerticalResolverManager;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind managers with string keys (facade uses these)
        $this->app->singleton('openai.manager', OpenAIManager::class);
        $this->app->singleton('scraper.manager', ScraperManager::class);
        $this->app->singleton('filevision.manager', FileVisionManager::class);
        $this->app->singleton('page_classifier.manager', PageClassifierManager::class);
        $this->app->singleton('page_parser.manager', PageParserManager::class);
        $this->app->singleton('scrape_policy_engine.manager', ScrapePolicyEngineManager::class);
        $this->app->singleton('vertical_resolver.manager', VerticalResolverManager::class);
        $this->app->singleton('intent_resolver.manager', IntentResolverManager::class);
        $this->app->singleton('vectordb.manager', VectorDBManager::class);
        $this->app->singleton('text_embedding.manager', TextEmbeddingManager::class);
        $this->app->singleton('search_engine.manager', SearchEngineManager::class);

        // Bind interfaces to the default driver (type-safe for dependency injection)
        $this->app->singleton(OpenAIClient::class, fn ($app) => $app['openai.manager']->driver());
        $this->app->singleton(Scraper::class, fn ($app) => $app['scraper.manager']->driver());
        $this->app->singleton(FileVision::class, fn ($app) => $app['filevision.manager']->driver());
        $this->app->singleton(Classifier::class, fn ($app) => $app['page_classifier.manager']->driver());
        $this->app->singleton(Parser::class, fn ($app) => $app['page_parser.manager']->driver());
        $this->app->singleton(ScrapePolicyEngine::class, fn ($app) => $app['scrape_policy_engine.manager']->driver());
        $this->app->singleton(VerticalResolver::class, fn ($app) => $app['vertical_resolver.manager']->driver());
        $this->app->singleton(IntentResolver::class, fn ($app) => $app['intent_resolver.manager']->driver());
        $this->app->singleton(VectorDB::class, fn ($app) => $app['vectordb.manager']->driver());
        $this->app->singleton(TextEmbeddingContract::class, fn ($app) => $app['text_embedding.manager']->driver());
        $this->app->singleton(SearchEngine::class, fn ($app) => $app['search_engine.manager']->driver());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();

        $this->registerMorphMap();
    }

    /**
     * Register the morph map with snake_case type names for all models.
     */
    protected function registerMorphMap(): void
    {
        $models = [
            Article::class,
            ArticleIntent::class,
            Client::class,
            File::class,
            Fileable::class,
            Intent::class,
            IntentKeyword::class,
            IntentPage::class,
            Keyword::class,
            Page::class,
            PageCount::class,
            PageRelation::class,
            PageTag::class,
            PageVertical::class,
            Snapshot::class,
            Source::class,
            SourceVertical::class,
            Tag::class,
            User::class,
            Vertical::class,
        ];

        $map = collect($models)->mapWithKeys(function (string $class) {
            return [Str::snake(class_basename($class)) => $class];
        })->all();

        Relation::morphMap($map);
    }
}
