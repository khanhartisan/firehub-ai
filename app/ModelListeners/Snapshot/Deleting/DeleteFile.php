<?php

namespace App\ModelListeners\Snapshot\Deleting;

use App\Models\Snapshot;
use Exception;
use Illuminate\Support\Facades\Storage;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class DeleteFile extends ModelListener implements ModelListenerInterface
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
        return Snapshot::class;
    }

    /**
     * The list of all the events to listen to.
     *
     * @return array<string>
     */
    public function events(): array
    {
        return ["deleting"];
    }

    /**
     * Handle the event.
     *
     * @param Snapshot $snapshot
     * @param string $event
     * @return void
     * @throws Exception
     */
    protected function _handle(Snapshot $snapshot, string $event): void
    {
        // Delete the file if it exists
        if ($filePath = $snapshot->file_path
            and Storage::exists($filePath)
            and !Storage::delete($filePath)
        ) {
            throw new Exception('Failed to delete file: '.$filePath);
        }
    }
}
