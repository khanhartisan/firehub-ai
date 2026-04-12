<?php

namespace App\Console\Commands\LiveTests;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\IntentResolver\Intentable;
use App\Contracts\IntentResolver\IntentableIntents;
use App\Contracts\IntentResolver\IntentResolver as IntentResolverContract;
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
        $drivers = array_keys(config('intentresolver.drivers'));
        $defaultIndex = array_search(config('intentresolver.default'), $drivers, true);
        $driver = $this->choice(
            'Select driver',
            $drivers,
            $defaultIndex === false ? 0 : $defaultIndex
        );

        $action = $this->choice(
            'Select action',
            ['resolve', 'guess_intent_keywords', 'infer_from_keywords', 'score_keywords', 'merge_intents'],
            0
        );

        $resolver = IntentResolver::driver($driver);

        if ($action === 'merge_intents') {
            $mergeOptions = [
                'Test merge: resolve the same sample .md twice',
                'Two samples: intent-resolver.md + intent2-resolver.md',
            ];
            $mergeMode = $this->choice('How to obtain the two primary intents?', $mergeOptions, 0);

            if ($mergeMode === $mergeOptions[0]) {
                $intentable = $this->intentableForDefaultSample();
                if ($intentable === null) {
                    return self::FAILURE;
                }

                $this->comment('Both resolves use: resources/sample-markdown/sample-markdown-for-intent-resolver.md');
                $this->info('Calling resolve(same sample) ×2 → mergeIntents() / Driver: '.$driver);

                $start1 = microtime(true);
                $resolved1 = $resolver->resolve($intentable);
                $this->info('Processing time (resolve #1): '.(microtime(true) - $start1).' seconds');

                $start2 = microtime(true);
                $resolved2 = $resolver->resolve($intentable);
                $this->info('Processing time (resolve #2): '.(microtime(true) - $start2).' seconds');
                $this->line('-----');

                if (! $this->runMergeIntentsAfterResolves(
                    $resolver,
                    $resolved1,
                    $resolved2,
                    'Primary intent from resolve #1 (same .md):',
                    'Primary intent from resolve #2 (same .md):'
                )) {
                    return self::FAILURE;
                }

                return self::SUCCESS;
            }

            $intentable1 = $this->loadIntentableFromResource(
                'live-tests/sample-page-for-intent-resolver-service.md',
                'sample-markdown/sample-markdown-for-intent-resolver.md'
            );
            $intentable2 = $this->loadIntentableFromResource(
                'live-tests/sample-markdown-for-intent2-resolver.md',
                'sample-markdown/sample-markdown-for-intent2-resolver.md'
            );

            if ($intentable1 === null || $intentable2 === null) {
                return self::FAILURE;
            }

            $this->comment('Intent A ← resources/sample-markdown/sample-markdown-for-intent-resolver.md');
            $this->comment('Intent B ← resources/sample-markdown/sample-markdown-for-intent2-resolver.md');

            $this->info('Calling resolve(sample A) → resolve(sample B) → mergeIntents() / Driver: '.$driver);

            $start1 = microtime(true);
            $resolved1 = $resolver->resolve($intentable1);
            $this->info('Processing time (resolve A): '.(microtime(true) - $start1).' seconds');

            $start2 = microtime(true);
            $resolved2 = $resolver->resolve($intentable2);
            $this->info('Processing time (resolve B): '.(microtime(true) - $start2).' seconds');
            $this->line('-----');

            if (! $this->runMergeIntentsAfterResolves(
                $resolver,
                $resolved1,
                $resolved2,
                'Primary intent from sample A:',
                'Primary intent from sample B:'
            )) {
                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $intentable = $this->intentableForDefaultSample();
        if ($intentable === null) {
            return self::FAILURE;
        }

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

    /**
     * @return Intentable|null Null when the resource file is missing.
     */
    private function intentableForDefaultSample(): ?Intentable
    {
        return $this->loadIntentableFromResource(
            'live-tests/sample-page-for-intent-resolver-service.md',
            'sample-markdown/sample-markdown-for-intent-resolver.md'
        );
    }

    /**
     * @return Intentable|null Null when the resource file is missing.
     */
    private function loadIntentableFromResource(string $storageKey, string $resourcePathRelative): ?Intentable
    {
        $resourcePath = resource_path($resourcePathRelative);

        if (! Storage::exists($storageKey)) {
            if (! is_file($resourcePath)) {
                $this->error("Missing sample fixture: {$resourcePath}");

                return null;
            }

            Storage::put($storageKey, file_get_contents($resourcePath));
        }

        return (new Intentable)->setContent(Storage::get($storageKey));
    }

    private function runMergeIntentsAfterResolves(
        IntentResolverContract $resolver,
        IntentableIntents $resolved1,
        IntentableIntents $resolved2,
        string $label1,
        string $label2,
    ): bool {
        $i1 = $resolved1->getPrimaryIntent();
        $i2 = $resolved2->getPrimaryIntent();

        if ($i1 === null || $i2 === null) {
            $this->error('resolve() returned no primary intent for one of the runs.');

            return false;
        }

        $this->info($label1);
        $this->displayIntent($i1);
        $this->line('-----');
        $this->info($label2);
        $this->displayIntent($i2);
        $this->line('-----');

        $mergeStart = microtime(true);
        $merged = $resolver->mergeIntents($i1, $i2);
        $this->info('Processing time (mergeIntents): '.(microtime(true) - $mergeStart).' seconds');
        $this->line('-----');

        if ($merged === null) {
            $this->warn('mergeIntents returned null (intents kept distinct).');
        } else {
            $this->info('Merged intent:');
            $this->displayIntent($merged);
        }

        return true;
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
