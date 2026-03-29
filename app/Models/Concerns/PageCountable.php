<?php

namespace App\Models\Concerns;

use App\Enums\ScrapableType;
use App\Enums\ScrapingStatus;
use App\Models\PageCount;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait PageCountable
{
    public function pageCounts(): HasMany
    {
        return $this
            ->hasMany(PageCount::class, 'countable_id')
            ->where('countable_type', $this->getMorphClass());
    }

    public function adjustPageCount(ScrapableType $scrapableType, ScrapingStatus $scrapingStatus, int $delta): bool
    {
        if ($delta === 0) {
            return true;
        }

        return !!PageCount::query()->upsert([
            [
                'id' => strtolower(Str::ulid()->toString()),
                'countable_type' => $this->getMorphClass(),
                'countable_id' => $this->getKey(),
                'scrapable_type' => $scrapableType,
                'scraping_status' => $scrapingStatus,
                'count' => $delta
            ]
        ], [
            'countable_type', 'countable_id', 'scrapable_type', 'scraping_status',
        ], [
            'count' => DB::raw('page_counts.count '
                .($delta > 0 ? '+' : '-').' '
                .abs($delta)
            )
        ]);
    }
}
