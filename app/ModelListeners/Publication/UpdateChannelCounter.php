<?php

namespace App\ModelListeners\Publication;

use App\Models\Publication;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class UpdateChannelCounter extends ModelListener implements ModelListenerInterface
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
        return ["created","deleted"];
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
        if ($event === 'created') {
            $publication->channel()->increment('publications_count');
        }

        if ($event === 'deleted') {
            $publication->channel()->decrement('publications_count');
        }
    }
}
