<?php

namespace App\Jobs;

use App\Enums\Queue;
use App\Models\Article;
use App\Models\Page;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ResolveIntent implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    protected Lock $manualLock;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue(Queue::PAGE_SCRAPING->value);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$lock = $this->getManualLock()
            or !$lock->get()
        ) {
            if (env('APP_DEBUG')) {
                dump('Could not acquire lock for '.static::class);
            }
            return;
        }

        $intentableModelClasses = [
            Page::class, Article::class
        ];

        // TODO: Implement the job

        $lock->release();
    }

    protected function getManualLock(): Lock
    {
        return $this->manualLock ??= Cache::lock(static::class);
    }
}
