<?php

namespace App\Console\Commands\LiveTests\Synthesizer\Illustration;

use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Facades\Synthesizer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestIllustrationService extends Command
{
    protected $signature = 'live-test:synthesizer:test-illustration-service
                            {--driver= : Synthesizer driver name}
                            {--content= : Raw article content used to build illustration contexts}
                            {--min-contexts=1 : Minimum number of contexts}
                            {--max-contexts=3 : Maximum number of contexts}
                            {--test= : What to test: contexts|direction|selection|generation|full}';

    protected $description = 'Run Director + Illustrator live flow (resolve contexts, direct, choose illustrator, generate files).';

    public function handle(): int
    {
        $drivers = array_keys(config('synthesizer.drivers', []));
        if ($drivers === []) {
            $this->error('No synthesizer drivers configured.');
            return self::FAILURE;
        }

        $requestedDriver = (string) ($this->option('driver') ?: config('synthesizer.default', 'basic'));
        if (! in_array($requestedDriver, $drivers, true)) {
            $this->error('Unknown synthesizer driver: '.$requestedDriver);
            return self::FAILURE;
        }

        $content = trim((string) $this->option('content'));
        if ($content === '') {
            $content = (string) $this->ask(
                'Article content for illustration planning',
                'AI onboarding for B2B SaaS teams improves activation when guidance is practical and contextual. '
                .'Teams should show setup steps, risk controls, and measurable KPIs in real product scenarios.'
            );
        }
        $content = trim($content);
        if ($content === '') {
            $this->error('Content cannot be empty.');
            return self::FAILURE;
        }

        [$minContexts, $maxContexts] = $this->resolveContextRange();

        $synthesizer = Synthesizer::driver($requestedDriver);
        $director = $synthesizer->getIllustrationDirector();
        $illustrators = $synthesizer->getIllustrators();

        if ($illustrators === []) {
            $this->error('No illustrators configured for synthesizer driver: '.$requestedDriver);
            return self::FAILURE;
        }

        $article = $this->buildArticleFromContent($content);

        $this->newLine();
        $this->info('Synthesizer driver: '.$requestedDriver);
        $this->line('Director: '.Str::afterLast($director::class, '\\'));
        $this->line('Illustrators: '.implode(', ', array_map(
            static fn ($illustrator) => Str::afterLast($illustrator::class, '\\'),
            $illustrators
        )));
        $this->line('-----');

        $testMode = $this->resolveTestMode();

        try {
            $contexts = $this->timedCall(
                'resolveIllustrationContexts',
                fn () => $director->resolveIllustrationContexts($article, $minContexts, $maxContexts)
            );

            if ($contexts === []) {
                $this->warn('No illustration contexts were produced.');
                return self::SUCCESS;
            }

            if ($testMode === 'contexts') {
                $this->displayContextRows($contexts);
                return self::SUCCESS;
            }

            $rows = [];
            foreach ($contexts as $index => $context) {
                if (! $context instanceof IllustrationContext) {
                    continue;
                }

                $direction = $this->timedCall(
                    'direct(context #'.($index + 1).')',
                    fn () => $director->direct($context)
                );

                if ($testMode === 'direction') {
                    $rows[] = [
                        '#'.($index + 1),
                        Str::limit((string) ($context->getSubjectValue() ?? ''), 48),
                        $direction->toJson(),
                    ];
                    continue;
                }

                $illustrator = $director->determineIllustrator($context, $direction, $illustrators);
                if ($illustrator === null) {
                    $rows[] = [
                        '#'.($index + 1),
                        (string) ($context->getSubjectValue() ?? ''),
                        '—',
                        'No illustrator selected',
                        '0',
                    ];
                    continue;
                }

                if ($testMode === 'selection') {
                    $rows[] = [
                        '#'.($index + 1),
                        Str::limit((string) ($context->getSubjectValue() ?? ''), 48),
                        Str::afterLast($illustrator::class, '\\'),
                        (string) ($illustrator->getIdentifier() ?? '—'),
                    ];
                    continue;
                }

                $result = $this->timedCall(
                    'generate(context #'.($index + 1).')',
                    fn () => $illustrator->generate($context, $direction)
                );

                $paths = array_map(
                    static fn ($file) => $file->getPath(),
                    $result->getFiles()
                );

                $rows[] = [
                    '#'.($index + 1),
                    Str::limit((string) ($context->getSubjectValue() ?? ''), 48),
                    Str::afterLast($illustrator::class, '\\'),
                    $paths !== [] ? implode("\n", $paths) : '—',
                    (string) count($paths),
                ];
            }

            if ($testMode === 'direction') {
                $this->table(['Context', 'Subject', 'Direction JSON'], $rows);
            } elseif ($testMode === 'selection') {
                $this->table(['Context', 'Subject', 'Selected illustrator', 'Identifier'], $rows);
            } else {
                $this->table(['Context', 'Subject', 'Illustrator', 'Saved files', 'File count'], $rows);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveTestMode(): string
    {
        $allowed = ['contexts', 'direction', 'selection', 'generation', 'full'];
        $requested = strtolower(trim((string) $this->option('test')));
        if ($requested !== '') {
            if (! in_array($requested, $allowed, true)) {
                $this->warn('Unknown --test mode "'.$requested.'". Falling back to interactive choice.');
            } else {
                return $requested;
            }
        }

        if (! $this->input->isInteractive()) {
            return 'full';
        }

        $choice = $this->choice(
            'What do you want to test?',
            ['full', 'contexts', 'direction', 'selection', 'generation'],
            'full'
        );

        return in_array($choice, ['contexts', 'direction', 'selection', 'generation', 'full'], true)
            ? $choice
            : 'full';
    }

    /**
     * @return array{0:int,1:int}
     */
    private function resolveContextRange(): array
    {
        $min = max(1, (int) $this->option('min-contexts'));
        $max = max($min, (int) $this->option('max-contexts'));

        if (! $this->input->isInteractive()) {
            return [$min, $max];
        }

        $minInput = (int) $this->ask('Minimum contexts to generate', (string) $min);
        $min = max(1, $minInput);

        $maxInput = (int) $this->ask('Maximum contexts to generate', (string) max($max, $min));
        $max = max($min, $maxInput);

        return [$min, $max];
    }

    /**
     * @param array<int, IllustrationContext> $contexts
     */
    private function displayContextRows(array $contexts): void
    {
        $rows = [];
        foreach ($contexts as $index => $context) {
            $rows[] = [
                '#'.($index + 1),
                Str::limit((string) ($context->getSubjectValue() ?? ''), 64),
                Str::limit((string) ($context->getGoalValue() ?? ''), 64),
                Str::limit((string) ($context->getStyleValue() ?? ''), 48),
                (string) ($context->getAspectRatioValue() ?? '—'),
            ];
        }

        $this->table(['Context', 'Subject', 'Goal', 'Style', 'Aspect ratio'], $rows);
    }

    private function buildArticleFromContent(string $content): Article
    {
        $paragraphs = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            preg_split("/\R+/", $content) ?: []
        )));

        $children = [];
        foreach ($paragraphs as $paragraph) {
            $children[] = (new Element())
                ->setType(ElementType::P)
                ->setChildren([$paragraph]);
        }

        if ($children === []) {
            $children[] = (new Element())->setType(ElementType::P)->setChildren([$content]);
        }

        return (new Article())->setChildren($children);
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    private function timedCall(string $label, callable $callback): mixed
    {
        $start = microtime(true);
        $this->info('Calling '.$label.'…');
        $result = $callback();
        $this->info(sprintf('Processing time (%s): %.3f s', $label, microtime(true) - $start));
        $this->line('-----');
        return $result;
    }
}

