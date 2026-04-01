<?php

namespace App\Jobs;

use App\Enums\Queue as QueueEnum;
use App\Models\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dispatches ScrapeFileJob after the current job finishes so ShouldBeUnique can release.
 */
class ScrapeFileJobDispatcher implements ShouldQueue
{
    use Queueable;

    protected File $file;

    public bool $deleteWhenMissingModels = true;

    public function __construct(File $file)
    {
        $this->file = $file->withoutRelations();

        $this->onQueue(QueueEnum::SCRAPING->value);
    }

    public function handle(): void
    {
        ScrapeFileJob::dispatch($this->file);
    }
}
