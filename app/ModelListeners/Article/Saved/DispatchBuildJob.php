<?php

namespace App\ModelListeners\Article\Saved;

use App\Enums\ArticleStatus;
use App\Enums\Queue;
use App\Jobs\BuildArticleJob;
use App\Models\Article;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class DispatchBuildJob extends ModelListener implements ModelListenerInterface
{
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
        return ["saved"];
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
        if (!$article->wasChanged('status')) {
            return;
        }

        if ($article->status !== ArticleStatus::PROCESSING) {
            return;
        }

        if (!Queue::ARTICLE_BUILDING->canDispatch()) {
            return;
        }

        BuildArticleJob::dispatch($article);
    }
}
