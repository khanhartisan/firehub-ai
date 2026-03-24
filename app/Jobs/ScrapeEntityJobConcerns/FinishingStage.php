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
        if (env('APP_DEBUG')) {
            dump('Finishing, entity '.$entity->id);
        }

        $this->markEntitySuccess($entity);
        return true;
    }
}