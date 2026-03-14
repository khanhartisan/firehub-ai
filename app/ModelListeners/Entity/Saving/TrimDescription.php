<?php

namespace App\ModelListeners\Entity\Saving;

use App\Models\Entity;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class TrimDescription extends ModelListener implements ModelListenerInterface
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
        return Entity::class;
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
     * @param Entity $entity
     * @param string $event
     * @return void
     */
    protected function _handle(Entity $entity, string $event): void
    {
        if (strlen((string) $entity->description) > 1024) {
            $entity->description = substr((string) $entity->description, 0, 1021) . '...';
        }
    }
}
