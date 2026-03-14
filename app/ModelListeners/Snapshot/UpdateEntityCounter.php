<?php

namespace App\ModelListeners\Snapshot;

use App\Models\Snapshot;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class UpdateEntityCounter extends ModelListener implements ModelListenerInterface
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
        return ["created","deleted"];
    }

    /**
     * Handle the event.
     *
     * @param Snapshot $snapshot
     * @param string $event
     * @return void
     */
    protected function _handle(Snapshot $snapshot, string $event): void
    {
        if ($event === 'created') {
            $snapshot->entity()->incrementEach([
                'snapshots_count' => 1,
                'version_index' => 1
            ]);
        }

        if ($event === 'deleted') {
            $snapshot->entity()->decrement('snapshots_count');
        }
    }
}
