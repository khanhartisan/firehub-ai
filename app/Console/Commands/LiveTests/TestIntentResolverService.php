<?php

namespace App\Console\Commands\LiveTests;

use App\Contracts\IntentResolver\IntentData;
use App\Facades\IntentResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestIntentResolverService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live-test:test-intent-resolver-service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run IntentResolver against sample HTML (interactive driver; calls OpenAI/Gemma).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $samplePath = 'live-tests/sample-page-for-intent-resolver-service.md';
        $htmlResourcePath = resource_path('sample-markdown/sample-markdown-for-intent-resolver.md');

        if (! Storage::exists($samplePath)) {
            if (! is_file($htmlResourcePath)) {
                $this->error("Missing sample HTML fixture: {$htmlResourcePath}");

                return self::FAILURE;
            }

            Storage::put($samplePath, file_get_contents($htmlResourcePath));
        }

        $html = Storage::get($samplePath);

        $drivers = array_keys(config('intentresolver.drivers'));
        $defaultIndex = array_search(config('intentresolver.default'), $drivers, true);
        $driver = $this->choice(
            'Select driver',
            $drivers,
            $defaultIndex === false ? 0 : $defaultIndex
        );

        $action = $this->choice(
            'Select action',
            ['resolve', 'guess_keywords'],
            0
        );

        $resolver = IntentResolver::driver($driver);

        if ($action === 'resolve') {
            $start = microtime(true);
            $this->info('Calling IntentResolver::resolve() / Driver: '.$driver);
            $intent = $resolver->resolve($html);
            $this->info('Processing time: '.(microtime(true) - $start).' seconds');
            $this->line('-----');
            $this->displayIntent($intent);

            return self::SUCCESS;
        }

        $start = microtime(true);
        $this->info('Calling IntentResolver::resolve() then guessKeywords() / Driver: '.$driver);
        $intent = $resolver->resolve($html);
        $this->info('Processing time (resolve): '.(microtime(true) - $start).' seconds');
        $this->line('-----');
        $this->displayIntent($intent);
        $this->line('-----');

        $kwStart = microtime(true);
        $keywords = $resolver->guessKeywords($intent);
        $this->info('Processing time (guessKeywords): '.(microtime(true) - $kwStart).' seconds');
        $this->line('-----');

        if ($keywords === []) {
            $this->warn('No keywords returned.');
        } else {
            $rows = [];
            foreach ($keywords as $index => $keyword) {
                $relevance = $keyword->getRelevance();
                $rows[] = [
                    (string) ($index + 1),
                    $keyword->getKeyword(),
                    $relevance !== null ? (string) $relevance : '',
                ];
            }
            $this->table(['#', 'keyword', 'relevance'], $rows);
        }

        return self::SUCCESS;
    }

    private function displayIntent(IntentData $intent): void
    {
        $types = array_map(
            static fn ($type) => $type->name.' ('.$type->value.')',
            $intent->getTypes()
        );

        $language = $intent->getLanguage();

        $this->table(
            ['Field', 'Value'],
            [
                ['title', $intent->getTitle() ?? ''],
                ['description', $intent->getDescription()],
                ['language', $language !== null ? $language->value.' ('.$language->name.')' : ''],
                ['types', $types !== [] ? implode(', ', $types) : '(none)'],
            ]
        );
    }
}
