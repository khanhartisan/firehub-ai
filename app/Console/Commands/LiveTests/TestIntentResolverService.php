<?php

namespace App\Console\Commands\LiveTests;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\IntentResolver\Intentable;
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
        $intentable = (new Intentable)->setContent($html);

        $drivers = array_keys(config('intentresolver.drivers'));
        $defaultIndex = array_search(config('intentresolver.default'), $drivers, true);
        $driver = $this->choice(
            'Select driver',
            $drivers,
            $defaultIndex === false ? 0 : $defaultIndex
        );

        $action = $this->choice(
            'Select action',
            ['resolve', 'guess_intent_keywords', 'infer_from_keywords', 'score_keywords'],
            0
        );

        $resolver = IntentResolver::driver($driver);

        if ($action === 'resolve') {
            $start = microtime(true);
            $this->info('Calling IntentResolver::resolve() / Driver: '.$driver);
            $resolved = $resolver->resolve($intentable);
            $this->info('Processing time: '.(microtime(true) - $start).' seconds');
            $this->line('-----');

            foreach ($resolved->getIntentableIntents() as $i => $row) {
                $rel = $row->getRelevance();
                $this->info('Intent '.($i + 1).' (relevance: '.($rel !== null ? (string) $rel : 'null').')');
                $this->displayIntent($row->getIntent());
                $this->line('-----');
            }

            return self::SUCCESS;
        }

        if ($action === 'score_keywords') {
            $start = microtime(true);
            $this->info('Calling IntentResolver::resolve() then scoreKeywords() / Driver: '.$driver);
            $intent = $resolver->resolve($intentable)->getPrimaryIntent();
            if ($intent === null) {
                $this->error('Resolve returned no primary intent.');

                return self::FAILURE;
            }
            $this->info('Processing time (resolve): '.(microtime(true) - $start).' seconds');
            $this->line('-----');
            $this->displayIntent($intent);
            $this->line('-----');

            $sampleKeywords = [
                'best product reviews 2026',
                'buy online discount',
                'how to choose guide',
                'compare prices',
                'best movies to watch',
                'best films to watch',
                'best movies ever',
                'best films 2026',
                'best films this year',
                'best games 2026',
                'what should I watch with family',
            ];

            $scoreStart = microtime(true);
            $scored = $resolver->scoreKeywords($intent, $sampleKeywords);
            $this->info('Processing time (scoreKeywords): '.(microtime(true) - $scoreStart).' seconds');
            $this->comment('Scoring sample keywords: '.implode(', ', $sampleKeywords));
            $this->line('-----');

            if ($scored === []) {
                $this->warn('No scored keywords returned.');
            } else {
                $rows = [];
                foreach ($scored as $index => $keyword) {
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

        if ($action === 'infer_from_keywords') {
            $sampleKeywords = [
                'best running shoes 2026',
                'marathon training plan',
                'cheap sneakers free shipping',
                'how to improve 5k time',
                'best movies to watch',
                'best movies ever',
                'best films 2026',
            ];

            $start = microtime(true);
            $this->info('Calling IntentResolver::inferFromKeywords() / Driver: '.$driver);
            $this->comment('Sample keywords: '.implode(', ', $sampleKeywords));

            $groups = $resolver->inferFromKeywords($sampleKeywords);
            $this->info('Processing time: '.(microtime(true) - $start).' seconds');
            $this->line('-----');

            if ($groups === []) {
                $this->warn('No intent groups returned.');
            } else {
                foreach ($groups as $i => $group) {
                    $this->info('Group '.($i + 1));
                    $this->displayIntent($group->getIntent());
                    $rows = [];
                    foreach ($group->getKeywords() as $j => $keyword) {
                        $relevance = $keyword->getRelevance();
                        $rows[] = [
                            (string) ($j + 1),
                            $keyword->getKeyword(),
                            $relevance !== null ? (string) $relevance : '',
                        ];
                    }
                    $this->table(['#', 'keyword', 'relevance'], $rows);
                    $this->line('-----');
                }
            }

            return self::SUCCESS;
        }

        $start = microtime(true);
        $this->info('Calling IntentResolver::resolve() then guessIntentKeywords() / Driver: '.$driver);
        $intent = $resolver->resolve($intentable)->getPrimaryIntent();
        if ($intent === null) {
            $this->error('Resolve returned no primary intent.');

            return self::FAILURE;
        }
        $this->info('Processing time (resolve): '.(microtime(true) - $start).' seconds');
        $this->line('-----');
        $this->displayIntent($intent);
        $this->line('-----');

        $kwStart = microtime(true);
        $keywords = $resolver->guessIntentKeywords($intent);
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

    private function displayIntent(Intent $intent): void
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
