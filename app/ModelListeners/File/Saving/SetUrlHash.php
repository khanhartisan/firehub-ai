<?php

namespace App\ModelListeners\File\Saving;

use App\Models\File;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class SetUrlHash extends ModelListener implements ModelListenerInterface
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
        return File::class;
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
     * @param File $file
     * @param string $event
     * @return void
     */
    protected function _handle(File $file, string $event): void
    {
        $file->url_hash = File::getUrlHash($file->url);
    }
}
