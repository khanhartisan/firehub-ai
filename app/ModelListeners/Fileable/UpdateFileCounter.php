<?php

namespace App\ModelListeners\Fileable;

use App\Models\Fileable;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class UpdateFileCounter extends ModelListener implements ModelListenerInterface
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
        return Fileable::class;
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
     * @param Fileable $fileable
     * @param string $event
     * @return void
     */
    protected function _handle(Fileable $fileable, string $event): void
    {
        if ($event === 'created') {
            $fileable->file()->increment('fileables_count');
        }

        if ($event === 'deleted') {
            $fileable->file()->decrement('fileables_count');
        }
    }
}
