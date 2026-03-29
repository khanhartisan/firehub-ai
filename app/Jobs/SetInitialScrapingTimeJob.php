<?php

namespace App\Jobs;

use App\Enums\ScrapingStatus;
use App\Facades\ScrapePolicyEngine;
use App\Models\Page;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SetInitialScrapingTimeJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $uniqueFor = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = time();
        foreach (ScrapingStatus::cases() as $status) {
            if ($status === ScrapingStatus::FAILED) {
                continue;
            }

            // Pick a page
            while ($page = Page::query()
                ->where('scraping_status', $status)
                ->whereNull('next_scrape_at')
                ->first()
            ) {
                $page->update([
                    'next_scrape_at' => ScrapePolicyEngine::calculateInitialScrapingTime($page),
                ]);

                if (time() - $startTime >= $this->timeout - 5) {
                    return;
                }
            }
        }
    }
}
