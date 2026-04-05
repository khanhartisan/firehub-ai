<?php

namespace App\Console\Commands\LiveTests;

use App\Facades\PageClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestPageClassifier extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live-test:test-page-classifier';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run PageClassifier against sample HTML (interactive driver).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $samplePath = 'live-tests/sample-page-for-page-classifier.html';
        $resourcePath = resource_path('sample-html/sample-page-for-page-classifier.html');

        if (! Storage::exists($samplePath)) {
            if (! is_file($resourcePath)) {
                $this->error("Missing sample HTML fixture: {$resourcePath}");

                return self::FAILURE;
            }

            Storage::put($samplePath, file_get_contents($resourcePath));
        }

        $html = Storage::get($samplePath);

        $drivers = array_keys(config('pageclassifier.drivers'));
        $defaultIndex = array_search(config('pageclassifier.default'), $drivers, true);
        $driver = $this->choice(
            'Select driver',
            $drivers,
            $defaultIndex === false ? 0 : $defaultIndex
        );

        $start = microtime(true);
        $this->info('Calling PageClassifier::classify() / Driver: '.$driver);
        $result = PageClassifier::driver($driver)->classify($html);
        $this->info('Processing time: '.(microtime(true) - $start).' seconds');
        $this->line('-----');

        $this->table(
            ['Field', 'Value'],
            [
                ['content_type', $result->getContentType()?->value ?? '—'],
                ['page_type', $result->getPageType()?->value ?? '—'],
                ['temporal', $result->getTemporal()?->value ?? '—'],
                ['tags', implode(', ', $result->getTags())],
            ]
        );

        return self::SUCCESS;
    }
}
