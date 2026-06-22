<?php

use App\Jobs\ScheduleEmbeddingJob;
use App\Jobs\ScheduleScrapeDueJob;
use App\Jobs\ScrapeSourcesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use KhanhArtisan\LaravelBackbone\RelationCascade\Jobs\CascadeDelete;
use KhanhArtisan\LaravelBackbone\RelationCascade\Jobs\CascadeRestore;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scraping scheduler job
|--------------------------------------------------------------------------
| Self-dispatches immediately so it runs again right away.
| - ShouldBeUniqueUntilProcessing: only one job in queue at a time (no spam;
|   schedule or duplicate workers cannot flood the queue).
| - In-job Cache::lock: only one execution at a time (no race with multiple workers).
| Schedule runs every minute as a safety net (e.g. after deploy).
*/
Schedule::job(new ScheduleScrapeDueJob(limit: 50))->everyMinute();

/*
|--------------------------------------------------------------------------
| ScrapeSourcesJob: home-page scrape when source has nothing planned
|--------------------------------------------------------------------------
| For each source: if it has any planned (due) scrape page, skip. Otherwise
| ensure a page exists for its base_url and dispatch ScrapePageJob (home page).
*/
Schedule::job(new ScrapeSourcesJob)->everyMinute();

/*
|--------------------------------------------------------------------------
| Embedding scheduler
|--------------------------------------------------------------------------
| Walks the Eloquent morph map for EmbeddableModel subclasses and queues
| EmbeddingJob for rows that are embeddable but not yet embedded.
*/
Schedule::job(new ScheduleEmbeddingJob(perModelLimit: 100))->everyMinute();

Schedule::job(new \App\Jobs\SetInitialScrapingTimeJob())->everyMinute();

Schedule::job(new \App\Jobs\ResolveIntentJob())->everyMinute();

// Cascading jobs
Schedule::job(new CascadeDelete)->everyMinute();
Schedule::job(new CascadeRestore)->everyMinute();

// Publishing jobs
Schedule::job(new \App\Jobs\DispatchPublishingJob())->everyMinute();

// Force delete files
Schedule::job(new \App\Jobs\ForceDeleteFiles())->everyMinute();