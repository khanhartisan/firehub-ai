<?php

namespace App\ModelListeners\ArticleIntent;

use App\Models\ArticleIntent;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class UpdateCounter extends ModelListener implements ModelListenerInterface
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
        return ArticleIntent::class;
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
     * @param ArticleIntent $articleIntent
     * @param string $event
     * @return void
     */
    protected function _handle(ArticleIntent $articleIntent, string $event): void
    {
        if ($event === 'created') {
            $articleIntent->article()->increment('intents_count');
            $articleIntent->intent()->increment('articles_count');
        }

        if ($event === 'deleted') {
            $articleIntent->article()->decrement('intents_count');
            $articleIntent->intent()->decrement('articles_count');
        }
    }
}
