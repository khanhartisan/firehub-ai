<?php

namespace App\Database\Eloquent\Relations;

use App\Contracts\Model\EntityCountable;
use App\Models\Entity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EntityCountBelongsToMany extends BelongsToMany
{
    private bool $syncEntityCounts = false;

    public function syncEntityCounts(bool $enabled = true): static
    {
        $this->syncEntityCounts = $enabled;
        return $this;
    }

    public function attach($ids, array $attributes = [], $touch = true): void
    {
        $ids = $this->parseIds($ids);

        parent::attach($ids, $attributes, $touch);

        $this->adjustEntityCountsForRelatedIds($ids, 1);
    }

    public function detach($ids = null, $touch = true): int
    {
        $idsToDetach = $ids === null
            ? $this->allRelatedIds()->all()
            : $this->parseIds($ids);

        $result = parent::detach($ids, $touch);

        $this->adjustEntityCountsForRelatedIds($idsToDetach, -1);

        return $result;
    }

    public function sync($ids, $detaching = true): array
    {
        $changes = parent::sync($ids, $detaching);

        $this->adjustEntityCountsForRelatedIds($changes['attached'] ?? [], 1);
        $this->adjustEntityCountsForRelatedIds($changes['detached'] ?? [], -1);

        return $changes;
    }

    private function adjustEntityCountsForRelatedIds(array $relatedIds, int $delta): void
    {
        if (!$this->syncEntityCounts || $delta === 0 || $relatedIds === []) {
            return;
        }

        $entity = $this->getParent();

        if (!$entity instanceof Entity || $entity->type === null || $entity->scraping_status === null) {
            return;
        }

        /** @var Collection<int, mixed> $relatedModels */
        $relatedModels = $this->getRelated()
            ->newQuery()
            ->whereKey($relatedIds)
            ->get();

        foreach ($relatedModels as $relatedModel) {
            if (!$relatedModel instanceof EntityCountable) {
                continue;
            }

            $relatedModel->adjustEntityCount($entity->type, $entity->scraping_status, $delta);
        }
    }
}

