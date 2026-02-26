<?php

namespace App\Contracts\Model;

use App\Enums\EntityType;
use App\Enums\ScrapingStatus;

/**
 * Contract for resources that maintain entity counts by type and scraping status.
 *
 * Implemented by Vertical, Source, and Tag. When entities are created, updated,
 * or deleted, EntityCountListener calls this to keep entity_counts in sync for
 * dashboards and filtering.
 *
 * @see \App\ModelListeners\Entity\EntityCountListener
 */
interface EntityCountable
{
    /**
     * Adjust the count for the given entity type and scraping status.
     *
     * @param  EntityType  $entityType  e.g. PAGE, UNCLASSIFIED
     * @param  ScrapingStatus  $scrapingStatus  e.g. SUCCESS, PENDING
     * @param  int  $delta  Change amount (e.g. +1 on create, -1 on delete)
     * @return bool  Whether the adjustment was applied
     */
    public function adjustEntityCount(EntityType $entityType, ScrapingStatus $scrapingStatus, int $delta): bool;
}