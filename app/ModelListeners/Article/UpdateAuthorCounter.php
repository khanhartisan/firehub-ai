<?php

namespace App\ModelListeners\Article;

use App\Models\Article;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class UpdateAuthorCounter extends ModelListener implements ModelListenerInterface
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
        return ["created","deleted"];
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
        if ($event === 'created') {
            $article->author()->increment('articles_count');
        }

        if ($event === 'deleted') {
            $article->author()->decrement('articles_count');
        }
    }
}
