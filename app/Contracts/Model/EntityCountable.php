<?php

namespace App\Contracts\Model;

use App\Enums\EntityType;
use App\Enums\ScrapingStatus;

interface EntityCountable
{
    public function adjustEntityCount(EntityType $entityType, ScrapingStatus $scrapingStatus, int $delta): bool;
}