<?php

namespace App\ModelListeners\Article\Saving;

use App\Models\Article;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class ResetIntentResolvedAt extends ModelListener implements ModelListenerInterface
{
    /**
     * Listeners with higher priority will run first.
     *
     * @return int
     */
    public function priority(): int
    {
        return -100;
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
        if (!$article->is_embeddable or !$article->is_embedded) {
            $article->intent_resolved_at = null;
        }
    }
}
