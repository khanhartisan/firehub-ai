<?php

namespace App\Console\Commands\LiveTests;

use App\Facades\PageParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TestPageParserService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live-test:test-page-parser-service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run PageParser against sample HTML (interactive driver).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $samplePath = 'live-tests/sample-page-for-page-parser-service.html';
        $resourcePath = resource_path('sample-html/sample-page-for-page-classifier.html');

        if (! Storage::exists($samplePath)) {
            if (! is_file($resourcePath)) {
                $this->error("Missing sample HTML fixture: {$resourcePath}");

                return self::FAILURE;
            }

            Storage::put($samplePath, file_get_contents($resourcePath));
        }

        $html = Storage::get($samplePath);

        $drivers = array_keys(config('pageparser.drivers'));
        $defaultIndex = array_search(config('pageparser.default'), $drivers, true);
        $driver = $this->choice(
            'Select driver',
            $drivers,
            $defaultIndex === false ? 0 : $defaultIndex
        );

        $start = microtime(true);
        $this->info('Calling PageParser::parse() / Driver: '.$driver);
        $result = PageParser::driver($driver)->parse($html);
        $this->info('Processing time: '.(microtime(true) - $start).' seconds');
        $this->line('-----');

        $linked = $result->getLinkedPageUrls();
        $linkedPreview = $linked === []
            ? '—'
            : implode(', ', array_slice($linked, 0, 8)).(count($linked) > 8 ? ' …' : '');

        $this->table(
            ['Field', 'Value'],
            [
                ['title', $result->getTitle() ?: '—'],
                ['excerpt', $result->getExcerpt() ?: '—'],
                ['thumbnail_url', $result->getThumbnailUrl() ?: '—'],
                ['canonical_url', $result->getCanonicalUrl() ?: '—'],
                ['canonical_number', $result->getCanonicalNumber() !== null ? (string) $result->getCanonicalNumber() : '—'],
                ['published_at', $result->getPublishedAt()?->toIso8601String() ?? '—'],
                ['updated_at', $result->getUpdatedAt()?->toIso8601String() ?? '—'],
                ['fetched_at', $result->getFetchedAt()?->toIso8601String() ?? '—'],
                ['linked_page_urls', $linkedPreview],
                ['markdown_preview', Str::limit($result->getMarkdownContent(), 800) ?: '—'],
            ]
        );

        return self::SUCCESS;
    }
}
