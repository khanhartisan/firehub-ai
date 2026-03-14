<?php

namespace App\Jobs\ScrapeEntityJobConcerns;

use App\Enums\ScrapingStatus;
use App\Models\Entity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait FinishingStage
{
    protected function finish(Entity $entity): bool
    {
        $entity->scraping_status = ScrapingStatus::SUCCESS;
        $entity->attempts = 0;
        $entity->fetched_at = Carbon::now();

        $saved = false;
        DB::transaction(function () use (&$saved, $entity) {
            $saved = $entity->save();
        });

        return $saved;
    }
}