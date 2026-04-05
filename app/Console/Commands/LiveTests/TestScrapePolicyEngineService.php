<?php

namespace App\Console\Commands\LiveTests;

use App\Facades\ScrapePolicyEngine;
use App\Models\Page;
use Illuminate\Console\Command;

class TestScrapePolicyEngineService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live-test:test-scrape-policy-engine-service
                            {page? : The page ID to evaluate (from your database)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run ScrapePolicyEngine::evaluate() for a Page (interactive driver).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $pageIdRaw = $this->argument('page') ?? $this->ask('Page ID', Page::query()->first()->id);
        $pageId = (int) $pageIdRaw;

        if ($pageId < 1) {
            $this->error('Page ID must be a positive integer.');

            return self::FAILURE;
        }

        $page = Page::query()->find($pageId);

        if ($page === null) {
            $this->error("No page found for ID [{$pageId}].");

            return self::FAILURE;
        }

        $drivers = array_keys(config('scrapepolicyengine.drivers'));
        $defaultIndex = array_search(config('scrapepolicyengine.default'), $drivers, true);
        $driver = $this->choice(
            'Select driver',
            $drivers,
            $defaultIndex === false ? 0 : $defaultIndex
        );

        $start = microtime(true);
        $this->info('Calling ScrapePolicyEngine::evaluate() / Driver: '.$driver);
        $this->line("Page [{$page->id}]: {$page->url}");

        $result = ScrapePolicyEngine::driver($driver)->evaluate($page);
        $this->info('Processing time: '.(microtime(true) - $start).' seconds');
        $this->line('-----');

        $this->table(
            ['Field', 'Value'],
            [
                ['next_scrape_at', $result->getNextScrapeAt()?->toIso8601String() ?? '—'],
                ['change_boost', (string) $result->getChangeBoost()],
                ['value_boost', (string) $result->getValueBoost()],
                ['error_penalty', (string) $result->getErrorPenalty()],
                ['priority', (string) $result->getPriority()],
                ['urgency', (string) $result->getUrgency()],
                ['cost_factor', (string) $result->getCostFactor()],
            ]
        );

        return self::SUCCESS;
    }
}
