<?php

namespace App\ModelListeners\Article\Creating;

use App\Enums\ArticleStatus;
use App\Models\Article;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class CheckCanCreateNew extends ModelListener implements ModelListenerInterface
{
    protected const int MAX_CONCURRENT_ARTICLES = 10;

    /**
     * Listeners with higher priority will run first.
     *
     * @return int
     */
    public function priority(): int
    {
        return 0;
    }

    /**
     * Listen to the events of the given model.
     *
     * @return string
     */
    public function modelClass(): string
    {
        return Article::class;
    }

    /**
     * The list of all the events to listen to.
     *
     * @return array<string>
     */
    public function events(): array
    {
        return ["creating"];
    }

    /**
     * Handle the event.
     *
     * @param Article $article
     * @param string $event
     * @return void
     */
    protected function _handle(Article $article, string $event): void
    {
        $concurrentArticlesCount = Article::query()
            ->where('client_id', $article->client_id)
            ->whereIn('status', [
                ArticleStatus::UNREADY,
                ArticleStatus::READY,
            ])
            ->count();

        if ($concurrentArticlesCount >= self::MAX_CONCURRENT_ARTICLES) {
            abort('Maximum number of concurrent articles is exceeded.');
        }
    }
}
