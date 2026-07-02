<?php

namespace App\ModelListeners\Publication\Creating;

use App\Models\Publication;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class CheckChannelReference extends ModelListener implements ModelListenerInterface
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
        return ["creating"];
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
        if (!$publication->channel?->reference) {
            $message = 'Channel is not ready for publishing (missing reference)';
            if (!app()->runningInConsole()) {
                abort(409, $message);
            }

            throw new \Exception($message);
        }
    }
}
