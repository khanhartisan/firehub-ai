<?php

namespace App\ModelListeners\Article\Saved;

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\Publication;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class SyncPublicationStatus extends ModelListener implements ModelListenerInterface
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

        // If the article is completed
        if ($article->status->isCompleted()) {

            // And there's publication awaiting
            $awaitingQuery = $article
                ->publications()
                ->where('status', PublicationStatus::AWAITING);
            if ($awaitingQuery->exists()) {

                // Then we set them pending
                $awaitingQuery
                    ->get()
                    ->each(function (Publication $publication) use ($article) {
                        $publication->setRelation('publishable', $article);
                        $publication->status = PublicationStatus::PENDING;
                        $publication->save();
                    });
            }
        }
        // If the article isn't completed
        else {

            // And there's publication not awaiting
            $notAwaitingQuery = $article
                ->publications()
                ->where('status', '!=', PublicationStatus::AWAITING);
            if ($notAwaitingQuery->exists()) {

                // Then we set them awaiting
                $notAwaitingQuery
                    ->get()
                    ->each(function (Publication $publication) use ($article) {
                        $publication->setRelation('publishable', $article);
                        $publication->status = PublicationStatus::AWAITING;
                        $publication->save();
                    });
            }
        }
    }
}
