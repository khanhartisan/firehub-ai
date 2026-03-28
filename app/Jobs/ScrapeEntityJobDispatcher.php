<?php

namespace App\Jobs;

use App\Enums\ScrapingStage;
use App\Models\Entity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * This job is an intermediate dispatcher to help the ScrapeEntityJob dispatches
 * itself while it's still remaining the ShouldBeUnique behavior
 */
class ScrapeEntityJobDispatcher implements ShouldQueue
{
    use Queueable;

    protected Entity $entity;

    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(Entity $entity)
    {
        $this->entity = $entity->withoutRelations();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ScrapeEntityJob::dispatch($this->entity);
    }
}
