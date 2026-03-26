<?php

namespace App\ModelListeners\Entity\Saving;

use App\Models\Entity;
use App\Utils\UrlNormalizer;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class NormalizeUrl extends ModelListener implements ModelListenerInterface
{
    /**
     * Listeners with higher priority will run first.
     */
    public function priority(): int
    {
        return 10;
    }

    /**
     * Listen to the events of the given model.
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
        return ['saving'];
    }

    /**
     * Handle the event.
     */
    protected function _handle(Entity $entity, string $event): void
    {
        $url = $entity->url;
        if ($url === null || $url === '') {
            return;
        }

        $normalized = UrlNormalizer::normalize((string) $url);
        if ($normalized !== $url) {
            $entity->url = $normalized;
        }
    }
}
