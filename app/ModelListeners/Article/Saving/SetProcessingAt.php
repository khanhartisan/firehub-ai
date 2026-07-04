<?php

namespace App\ModelListeners\Article\Saving;

use App\Enums\ArticleStatus;
use App\Models\Article;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class SetProcessingAt extends ModelListener implements ModelListenerInterface
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
        return ["saving"];
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
        if (!$article->isDirty('status')) {
            return;
        }

        if ($article->status !== ArticleStatus::PROCESSING) {
            return;
        }

        $article->processing_at = now();
    }
}
