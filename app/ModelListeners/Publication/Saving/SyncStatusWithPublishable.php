<?php

namespace App\ModelListeners\Publication\Saving;

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\Model;
use App\Models\Publication;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class SyncStatusWithPublishable extends ModelListener implements ModelListenerInterface
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
        return Publication::class;
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
     * @param Publication $publication
     * @param string $event
     * @return void
     */
    protected function _handle(Publication $publication, string $event): void
    {
        // Ignore if the status is in the retriable statuses
        // because those statuses will be handled by a scheduled job
        if ($publication->status->isRetriable()) {
            return;
        }

        /** @var Model $publishable */
        if (!$publishable = $publication->publishable) {
            return;
        }

        if ($publishable instanceof Article) {
            $this->handleArticle($publishable, $publication);
            return;
        }

        throw new \Exception('Unhandled publishable type.');
    }

    protected function handleArticle(Article $article, Publication $publication): void
    {
        // If the article is completed
        if ($article->status->isCompleted()) {
            if ($publication->status === PublicationStatus::AWAITING) {
                $publication->status = PublicationStatus::PENDING;
                return;
            }
        }
        // If the article isn't completed
        else {
            if ($publication->status !== PublicationStatus::AWAITING) {
                $publication->status = PublicationStatus::AWAITING;
                return;
            }
        }
    }
}
