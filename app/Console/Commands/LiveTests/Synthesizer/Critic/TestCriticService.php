<?php

namespace App\Console\Commands\LiveTests\Synthesizer\Critic;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Synthesizer\Critic\Critic;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Contracts\Synthesizer\Critic\Rectification;
use App\Facades\Synthesizer;
use App\Services\Synthesizer\Critic\CriticManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestCriticService extends Command
{
    protected $signature = 'live-test:synthesizer:test-critic-service
                            {--driver= : Critic driver name (basic, openai, openai_compatible)}
                            {--purpose= : Critic purpose (voice, structure, clarity, concision, fingerprint, evidence, hallucination, general)}
                            {--all-purposes : Run every purpose for the selected driver}';

    protected $description = 'Run Critic driver(s) in isolation (criticizeArticle) or load critics from a synthesizer driver.';

    public function handle(): int
    {
        $resolution = $this->resolveCritics();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$critics, $sourceLabel] = $resolution;
        $article = $this->buildArticle();
        $authorContext = $this->buildAuthorContext();
        $generalContext = $this->buildGeneralContext();
        $rectifications = $this->buildRectifications();

        $this->newLine();
        $this->info('Source: '.$sourceLabel);
        $this->info('Critics: '.implode(', ', array_map(
            static fn (Critic $critic): string => Str::afterLast($critic::class, '\\').' ('.$critic->getPurpose().')',
            $critics
        )));
        $this->displayArticleSummary($article);
        $this->displayContextSummary('Author context', $authorContext);
        $this->displayContextSummary('General context', $generalContext);
        $this->displayRectifications($rectifications);
        $this->line('-----');

        try {
            $allCriticisms = [];

            foreach ($critics as $critic) {
                $purpose = $critic->getPurpose();
                $criticisms = $this->timedCall(
                    "criticizeArticle ({$purpose})",
                    fn () => $critic->criticizeArticle(
                        $article,
                        $authorContext,
                        $generalContext,
                        $rectifications
                    )
                );
                $allCriticisms = array_merge($allCriticisms, $criticisms);
                $this->displayCriticisms($criticisms, $purpose);
            }

            $this->info('Total criticisms: '.count($allCriticisms));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * @return array{0: list<Critic>, 1: string}|null
     */
    private function resolveCritics(): ?array
    {
        if ($this->option('driver') !== null || $this->option('purpose') !== null || $this->option('all-purposes')) {
            return $this->resolveCriticsFromOptions();
        }

        $mode = $this->choice(
            'How should the critic be loaded?',
            [
                'Direct: CriticManager (single purpose or all purposes)',
                'From synthesizer driver: critics[] (profile wiring)',
            ],
            0
        );

        if (str_starts_with($mode, 'Direct')) {
            return $this->resolveCriticsFromManager();
        }

        return $this->resolveCriticsFromSynthesizer();
    }

    /**
     * @return array{0: list<Critic>, 1: string}|null
     */
    private function resolveCriticsFromOptions(): ?array
    {
        $manager = $this->laravel->make(CriticManager::class);
        $drivers = array_keys(config('synthesizer.critic.drivers', []));
        $purposes = $manager->purposes();

        $driver = (string) ($this->option('driver') ?? config('synthesizer.critic.default', 'basic'));
        if (! in_array($driver, $drivers, true)) {
            $this->error("Unknown critic driver [{$driver}].");

            return null;
        }

        if ($this->option('all-purposes')) {
            return [
                $manager->getCritics($driver),
                "CLI --driver={$driver} --all-purposes",
            ];
        }

        $purpose = (string) ($this->option('purpose') ?? $purposes[0] ?? 'clarity');
        if (! in_array($purpose, $purposes, true)) {
            $this->error("Unknown critic purpose [{$purpose}].");

            return null;
        }

        return [
            [$manager->makeCritic($purpose, $driver)],
            "CLI --driver={$driver} --purpose={$purpose}",
        ];
    }

    /**
     * @return array{0: list<Critic>, 1: string}|null
     */
    private function resolveCriticsFromManager(): ?array
    {
        $manager = $this->laravel->make(CriticManager::class);
        $drivers = array_keys(config('synthesizer.critic.drivers', []));
        if ($drivers === []) {
            $this->error('No critic drivers configured.');

            return null;
        }

        $defaultDriverIndex = array_search(config('synthesizer.critic.default'), $drivers, true);
        $driverName = $this->choice(
            'Critic driver',
            $drivers,
            $defaultDriverIndex === false ? 0 : $defaultDriverIndex
        );

        $scope = $this->choice(
            'Which critics to run?',
            [
                'Single purpose',
                'All purposes (voice, structure, clarity, concision, fingerprint, evidence, hallucination, general)',
            ],
            1
        );

        if (str_starts_with($scope, 'Single')) {
            $purposes = $manager->purposes();
            $purpose = $this->choice('Purpose', $purposes, 2);

            return [
                [$manager->makeCritic($purpose, $driverName)],
                "direct · makeCritic('{$purpose}', '{$driverName}')",
            ];
        }

        return [
            $manager->getCritics($driverName),
            "direct · getCritics('{$driverName}')",
        ];
    }

    /**
     * @return array{0: list<Critic>, 1: string}|null
     */
    private function resolveCriticsFromSynthesizer(): ?array
    {
        $drivers = array_keys(config('synthesizer.drivers'));
        if ($drivers === []) {
            $this->error('No synthesizer drivers configured.');

            return null;
        }

        $defaultDriverIndex = array_search(config('synthesizer.default'), $drivers, true);
        $driverName = $this->choice(
            'Select synthesizer driver',
            $drivers,
            $defaultDriverIndex === false ? 0 : $defaultDriverIndex
        );

        $critics = Synthesizer::driver($driverName)->getCritics();
        if ($critics === []) {
            $this->error('Synthesizer driver returned no critics.');

            return null;
        }

        return [
            $critics,
            "synthesizer driver «{$driverName}» · getCritics()",
        ];
    }

    private function buildArticle(): Article
    {
        $useFixture = $this->confirm('Use default sample article fixture?', true);
        if (! $useFixture) {
            $rootId = Str::limit((string) $this->ask('Article identifier (max 4 chars)', 'artl'), 4, '');
            $body = (string) $this->ask('Article body text', 'Sample draft body for critic review.');

            $article = (new Article)->setIdentifier($rootId);
            $article->addChild(
                (new Element)
                    ->setType(ElementType::P)
                    ->setIdentifier('body')
                    ->addChild($body)
            );

            return $article;
        }

        $article = (new Article)->setIdentifier('root');
        $article->addChild(
            (new Element)
                ->setType(ElementType::DIV)
                ->setIdentifier('intr')
                ->addChild('A short opener without much detail.')
        );
        $article->addChild(
            (new Element)
                ->setType(ElementType::DIV)
                ->setIdentifier('thin')
                ->addChild('Too brief.')
        );
        $article->addChild(
            (new Element)
                ->setType(ElementType::DIV)
                ->setIdentifier('main')
                ->addChild(str_repeat(
                    'This section has enough substance for clarity heuristics and structure review. ',
                    6
                ))
        );

        return $article;
    }

    private function buildAuthorContext(): ?SemanticContext
    {
        if (! $this->confirm('Include author context?', true)) {
            return null;
        }

        return (new SemanticContext)->set(
            'voice',
            'Author voice',
            (string) $this->ask(
                'Author voice value',
                'Practical founder tone with concrete examples'
            )
        );
    }

    private function buildGeneralContext(): ?SemanticContext
    {
        if (! $this->confirm('Include general context?', false)) {
            return null;
        }

        return (new SemanticContext)->set(
            'audience',
            'Target audience',
            (string) $this->ask('Audience', 'B2B SaaS operators evaluating onboarding tooling')
        );
    }

    /**
     * @return list<Rectification>
     */
    private function buildRectifications(): array
    {
        if (! $this->confirm('Pass last rectifications (skip re-criticizing those references)?', false)) {
            return [];
        }

        $reference = Str::limit((string) $this->ask('Rectified reference (max 4 chars)', 'thin'), 4, '');
        $adjustment = (string) $this->ask('Adjustment note', 'Expanded with examples and metrics.');

        return [
            (new Rectification)
                ->setReference($reference)
                ->setAdjustments([$adjustment]),
        ];
    }

    private function displayArticleSummary(Article $article): void
    {
        $this->info('Article fixture');
        $data = $article->toArray();
        $this->table(
            ['Field', 'Value'],
            [
                ['identifier', (string) ($data['identifier'] ?? '—')],
                ['children', (string) count($data['children'] ?? [])],
                ['markdown_preview', Str::limit($article->toMarkdown(), 160)],
            ]
        );
    }

    private function displayContextSummary(string $label, ?SemanticContext $context): void
    {
        if ($context === null) {
            $this->line("{$label}: (none)");

            return;
        }

        $this->info($label);
        $rows = [];
        foreach ($context->toArray() as $key => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $value = $entry['value'] ?? null;
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $rows[] = [
                $key,
                Str::limit((string) ($entry['description'] ?? ''), 40),
                Str::limit((string) ($value ?? ''), 72),
            ];
        }

        if ($rows !== []) {
            $this->table(['key', 'description', 'value'], $rows);
        }
    }

    /**
     * @param  list<Rectification>  $rectifications
     */
    private function displayRectifications(array $rectifications): void
    {
        if ($rectifications === []) {
            $this->line('Last rectifications: (none)');

            return;
        }

        $this->info('Last rectifications');
        $rows = [];
        foreach ($rectifications as $rectification) {
            $rows[] = [
                (string) ($rectification->getReference() ?? '—'),
                $rectification->getConfidence() !== null ? (string) $rectification->getConfidence() : '—',
                implode(' | ', $rectification->getAdjustments()),
            ];
        }
        $this->table(['reference', 'confidence', 'adjustments'], $rows);
    }

    /**
     * @param  list<Criticism>  $criticisms
     */
    private function displayCriticisms(array $criticisms, string $purpose): void
    {
        $this->info("Criticisms ({$purpose}): ".count($criticisms));

        if ($criticisms === []) {
            $this->line('(none)');
            $this->line('-----');

            return;
        }

        $rows = [];
        foreach ($criticisms as $index => $criticism) {
            $rows[] = [
                (string) ($index + 1),
                (string) ($criticism->getReference() ?? '—'),
                $criticism->getConfidence() !== null ? (string) $criticism->getConfidence() : '—',
                $criticism->getImportance() !== null ? (string) $criticism->getImportance() : '—',
                Str::limit(implode(' · ', $criticism->getRemarks()), 100),
            ];
        }

        $this->table(['#', 'reference', 'confidence', 'importance', 'remarks'], $rows);
        $this->line('-----');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function timedCall(string $label, callable $callback): mixed
    {
        $start = microtime(true);
        $this->info("Calling {$label}…");
        $result = $callback();
        $this->info(sprintf('Processing time (%s): %.3f s', $label, microtime(true) - $start));
        $this->line('-----');

        return $result;
    }
}
