<?php

namespace App\Models\Concerns;

use App\Enums\EntityType;
use App\Enums\ScrapingStatus;
use App\Models\EntityCount;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait EntityCountable
{
    public function entityCounts(): HasMany
    {
        return $this
            ->hasMany(EntityCount::class, 'countable_id')
            ->where('countable_type', $this->getMorphClass());
    }

    public function adjustEntityCount(EntityType $entityType, ScrapingStatus $scrapingStatus, int $delta): bool
    {
        if ($delta === 0) {
            return true;
        }

        return !!EntityCount::query()->upsert([
            [
                'id' => strtolower(Str::ulid()->toString()),
                'countable_type' => $this->getMorphClass(),
                'countable_id' => $this->getKey(),
                'entity_type' => $entityType,
                'scraping_status' => $scrapingStatus,
                'count' => $delta
            ]
        ], [
            'countable_type', 'countable_id', 'entity_type', 'scraping_status',
        ], [
            'count' => DB::raw('entity_counts.count '
                .($delta > 0 ? '+' : '-').' '
                .abs($delta)
            )
        ]);
    }
}