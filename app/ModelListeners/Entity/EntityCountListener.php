<?php

namespace App\ModelListeners\Entity;

use App\Contracts\Model\EntityCountable;
use App\Enums\EntityType;
use App\Enums\ScrapingStatus;
use App\Models\Entity;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListener;
use KhanhArtisan\LaravelBackbone\ModelListener\ModelListenerInterface;

class EntityCountListener extends ModelListener implements ModelListenerInterface
{
    /**
     * Listeners with higher priority will run first.
     *
     * @return int
     */
    public function priority(): int
    {
        return -10;
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
        return ['created', 'deleted', 'updated'];
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
        if ($event === 'created') {
            $this->incrementCounts($entity, 1);
            return;
        }

        if ($event === 'deleted') {
            $this->incrementCounts($entity, -1);
            return;
        }

        if ($event === 'updated') {
            if (!$entity->isDirty('type')
                and !$entity->isDirty('scraping_status')
            ) {
                return;
            }

            $oldType = $entity->getOriginal('type');
            $oldScrapingStatus = $entity->getOriginal('scraping_status');

            $this->adjustCounts($entity, $oldType, $oldScrapingStatus, -1);
            $this->incrementCounts($entity, 1);
        }
    }

    private function incrementCounts(Entity $entity, int $delta): void
    {
        if ($entity->type === null || $entity->scraping_status === null) {
            return;
        }
        $this->adjustCounts($entity, $entity->type, $entity->scraping_status, $delta);
    }

    private function adjustCounts(
        Entity $entity,
        EntityType $entityType,
        ScrapingStatus $scrapingStatus,
        int $delta
    ): void {
        foreach ($entity->getEntityCountableResources() as $resource) {
            if (!$resource instanceof EntityCountable) {
                continue;
            }
            $resource->adjustEntityCount($entityType, $scrapingStatus, $delta);
            /*dump($delta.' to '
                .$resource->getMorphClass().'@'.$resource->id
                .' / entity@'.$entity->id. ' / '.$entityType->name.' / '.$scrapingStatus->name);*/
            // TODO: Somehow we still have negative entity count for status FETCHING
            // Need investigation
        }
    }
}
