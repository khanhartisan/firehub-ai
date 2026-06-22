<?php

namespace App\ModelListeners\Fileable\Deleted;

use App\Models\File;
use App\Models\Fileable;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class DeleteOrphanFile extends ModelListener implements ModelListenerInterface
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
        return ["deleted"];
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
        // Skip if the file isn't orphan
        if (Fileable::query()
            ->where('file_id', $fileable->file_id)
            ->where('id', '!=', $fileable->id)
            ->exists()
        ) {
            return;
        }

        /** @var File $file */
        if (!$file = $fileable->file) {
            return;
        }

        // Delete the orphan file
        $file->delete();
    }
}
