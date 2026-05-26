<?php

namespace App\Console\Commands\LiveTests\Synthesizer\Writer;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\Critic\Critic;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Contracts\Synthesizer\Writer\Draft;
use App\Contracts\Synthesizer\Writer\RectifiedArticle;
use App\Contracts\Synthesizer\Writer\Writer;
use App\Facades\Synthesizer;
use App\Services\Synthesizer\Critic\CriticManager;
use App\Services\Synthesizer\Writer\WriterManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestWriterService extends Command
{
    protected $signature = 'live-test:synthesizer:test-writer-service
                            {--driver= : Writer driver name (basic, openai, openai_compatible) when loading directly}
                            {--synthesizer= : Synthesizer driver name when loading writer from a profile}
                            {--operation= : Operation: draft, rectify, or pipeline (draft → criticize → rectify)}';

    protected $description = 'Run a Writer in isolation (draft, rectifyArticle) or load writer from a synthesizer driver.';

    public function handle(): int
    {
        $resolution = $this->resolveWriter();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$writer, $sourceLabel] = $resolution;
        $operation = $this->resolveOperation();
        $authorContext = $this->buildAuthorContext();
        $generalContext = $this->buildSemanticContext();

        $this->newLine();
        $this->info('Writer: '.Str::afterLast($writer::class, '\\').' | '.$sourceLabel);
        $this->info('Operation: '.$operation);
        $this->displayContextSummary('Author context', $authorContext);
        $this->displayContextSummary('General context', $generalContext);
        $this->line('-----');

        try {
            $draft = null;
            $article = null;

            if (in_array($operation, ['draft', 'pipeline'], true)) {
                $brief = $this->buildBrief();
                $outline = $this->buildOutline();
                $draft = $this->timedCall(
                    'draft',
                    fn () => $writer->draft($brief, $outline, $authorContext, $generalContext)
                );
                $this->displayDraft($draft);
                $article = $draft->getArticle();
            }

            if (in_array($operation, ['rectify', 'pipeline'], true)) {
                if (! $article instanceof Article) {
                    $article = $this->buildArticleForRectify();
                    $this->displayArticleSummary($article, 'Article input for rectify');
                }

                $criticisms = $operation === 'pipeline'
                    ? $this->collectCriticismsViaCritics($article, $authorContext, $generalContext)
                    : $this->buildCriticisms($article, $authorContext, $generalContext);

                if ($criticisms === []) {
                    $this->warn('No criticisms to apply; skipping rectifyArticle.');

                    return self::SUCCESS;
                }

                $this->displayCriticisms($criticisms);
                $this->info('Rectify mode: '.$this->describeRectifyMode($criticisms));

                $rectified = $this->timedCall(
                    'rectifyArticle',
                    fn () => $writer->rectifyArticle($article, $criticisms, $authorContext, $generalContext)
                );
                $this->displayRectifiedArticle($rectified, $article);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    private function resolveOperation(): string
    {
        $fromOption = strtolower(trim((string) $this->option('operation')));
        if (in_array($fromOption, ['draft', 'rectify', 'pipeline'], true)) {
            return $fromOption;
        }

        $choice = $this->choice(
            'What should the writer run?',
            [
                'draft — brief + outline → Draft',
                'rectifyArticle — article + criticisms → RectifiedArticle',
                'pipeline — draft, critics, then rectifyArticle',
            ],
            0
        );

        if (str_contains($choice, 'pipeline')) {
            return 'pipeline';
        }

        if (str_contains($choice, 'rectifyArticle')) {
            return 'rectify';
        }

        return 'draft';
    }

    /**
     * @return array{0: Writer, 1: string}|null
     */
    private function resolveWriter(): ?array
    {
        $synthesizerDriver = $this->option('synthesizer');
        if (is_string($synthesizerDriver) && $synthesizerDriver !== '') {
            $drivers = array_keys(config('synthesizer.drivers', []));
            if (! in_array($synthesizerDriver, $drivers, true)) {
                $this->error("Unknown synthesizer driver [{$synthesizerDriver}].");

                return null;
            }

            return [
                Synthesizer::driver($synthesizerDriver)->getWriter(),
                "synthesizer driver «{$synthesizerDriver}» · getWriter()",
            ];
        }

        $writerDriver = $this->option('driver');
        if (is_string($writerDriver) && $writerDriver !== '') {
            $drivers = array_keys(config('synthesizer.writer.drivers', []));
            if (! in_array($writerDriver, $drivers, true)) {
                $this->error("Unknown writer driver [{$writerDriver}].");

                return null;
            }

            $manager = $this->laravel->make(WriterManager::class);

            return [
                $manager->driver($writerDriver),
                "direct · WriterManager::driver('{$writerDriver}')",
            ];
        }

        $mode = $this->choice(
            'How should the writer be loaded?',
            [
                'Direct: construct writer only (no Synthesizer)',
                'From synthesizer driver: Writer (full wiring)',
            ],
            0
        );

        if (str_starts_with($mode, 'Direct')) {
            $manager = $this->laravel->make(WriterManager::class);
            $driverName = $this->choice(
                'Which driver?',
                array_keys(config('synthesizer.writer.drivers', ['basic' => [], 'openai' => []])),
                array_search('openai', array_keys(config('synthesizer.writer.drivers', [])), true) ?: 0
            );

            return [
                $manager->driver($driverName),
                "direct · WriterManager::driver('{$driverName}')",
            ];
        }

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

        return [Synthesizer::driver($driverName)->getWriter(), "synthesizer driver «{$driverName}» · getWriter()"];
    }

    private function buildBrief(): Brief
    {
        $title = (string) $this->ask('Brief title', 'AI onboarding playbook for B2B SaaS teams');
        $description = (string) $this->ask(
            'Brief description',
            'How product and growth teams can roll out AI onboarding flows with measurable activation gains.'
        );
        $instructionsInput = (string) $this->ask(
            'Brief instructions (semicolon-separated)',
            'Keep claims grounded in source context; Prioritize practical takeaways; Keep structure concise and scannable'
        );

        $instructions = [];
        foreach (explode(';', $instructionsInput) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $instructions[] = $line;
            }
        }

        return (new Brief)
            ->setTitle($title)
            ->setDescription($description)
            ->setInstructions($instructions);
    }

    private function buildOutline(): Outline
    {
        return (new Outline)->setItems([
            (new OutlineItem)->setPoint(
                (new RelevantPoint)
                    ->setHeadline('Introduction')
                    ->setDescription('Why AI onboarding now matters for B2B SaaS teams.')
                    ->setEvidences(['Activation pressure is rising in competitive categories.'])
            )->setGuidelines(['Open with practical framing.']),
            (new OutlineItem)->setPoint(
                (new RelevantPoint)
                    ->setHeadline('Implementation framework')
                    ->setDescription('A step-by-step rollout framework from pilot to scale.')
                    ->setEvidences(['Start with one journey and measurable KPI.'])
            )->setGuidelines(['Use actionable steps and avoid generic advice.']),
            (new OutlineItem)->setPoint(
                (new RelevantPoint)
                    ->setHeadline('Risks and trade-offs')
                    ->setDescription('Common failure modes and how to mitigate them.')
                    ->setEvidences(['Over-automation can reduce trust if recommendations are noisy.'])
            )->setGuidelines(['Include caveats and balancing recommendations.']),
        ]);
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

    private function buildSemanticContext(): ?SemanticContext
    {
        if (! $this->confirm('Include general context?', true)) {
            return null;
        }

        $defaultClientContext = <<<'CTX'
Brand: GrowthOps Weekly
Positioning: Practical playbooks for SaaS product and growth leaders.
Tone preference: pragmatic, evidence-oriented, low fluff.
CTX;

        $defaultArticleContext = <<<'CTX'
Audience expects actionable onboarding frameworks, KPI examples, and risk controls.
Priority outcomes: faster activation, reduced time-to-value, improved trial-to-paid conversion.
CTX;

        $defaultWriterFocus = 'Keep examples concrete and include one compact checklist.';

        $context = new SemanticContext;
        $context->set(
            'client_context',
            'Client context DTO payload.',
            ['description' => ['description' => 'Overview', 'value' => (string) $this->ask('Client context', $defaultClientContext)]]
        );
        $context->set(
            'article_context',
            'Article-specific context DTO payload.',
            ['meta' => ['value' => ['raw_text' => (string) $this->ask('Article context', $defaultArticleContext)]]]
        );
        $context->set(
            'writer_focus',
            'Additional writing requirements.',
            (string) $this->ask('Writer focus', $defaultWriterFocus)
        );

        return $context;
    }

    private function buildArticleForRectify(): Article
    {
        $source = $this->choice(
            'Article source for rectifyArticle',
            [
                'Sample fixture (thin section + substantive section)',
                'Custom single-paragraph article',
            ],
            0
        );

        if (str_contains($source, 'Custom')) {
            $rootId = Str::limit((string) $this->ask('Article identifier (max 4 chars)', 'artl'), 4, '');
            $body = (string) $this->ask('Article body text', 'Sample draft body for writer rectification.');

            $article = (new Article)->setIdentifier($rootId);
            $article->addChild(
                (new Element)
                    ->setType(ElementType::P)
                    ->setIdentifier('body')
                    ->addChild($body)
            );

            return $article;
        }

        return $this->buildSampleArticleFixture();
    }

    private function buildSampleArticleFixture(): Article
    {
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

    /**
     * @return list<Criticism>
     */
    private function buildCriticisms(
        Article $article,
        ?SemanticContext $authorContext,
        ?SemanticContext $generalContext,
    ): array {
        $mode = $this->choice(
            'How should criticisms be supplied?',
            [
                'Sample fixture (referenced + optional article-wide)',
                'Run critic driver(s) on the article',
                'Enter one criticism manually',
            ],
            0
        );

        if (str_contains($mode, 'critic driver')) {
            return $this->collectCriticismsViaCritics($article, $authorContext, $generalContext);
        }

        if (str_contains($mode, 'manually')) {
            $reference = $this->ask('Reference (DOM identifier, max 4 chars; empty = article-wide)', 'thin');
            $reference = is_string($reference) ? Str::limit(trim($reference), 4, '') : '';
            $remark = (string) $this->ask('Remark', 'Expand with examples and measurable outcomes.');
            $purpose = (string) $this->ask('Critic purpose', 'clarity');

            $criticism = (new Criticism)
                ->setPurpose($purpose)
                ->setRemarks([$remark]);

            if ($reference !== '') {
                $criticism->setReference($reference);
            }

            return [$criticism];
        }

        return $this->buildSampleCriticismsFixture();
    }

    /**
     * @return list<Criticism>
     */
    private function buildSampleCriticismsFixture(): array
    {
        $includeArticleWide = $this->confirm(
            'Include one article-wide criticism (no reference)? Forces full-article rectify on OpenAI driver.',
            false
        );

        $criticisms = [
            (new Criticism)
                ->setPurpose('clarity')
                ->setReference('thin')
                ->setConfidence(0.85)
                ->setImportance(0.8)
                ->setRemarks(['Section is too thin; expand with supporting detail and examples.']),
            (new Criticism)
                ->setPurpose('voice')
                ->setReference('intr')
                ->setConfidence(0.7)
                ->setImportance(0.6)
                ->setRemarks(['Opening should sound more direct and practical.']),
        ];

        if ($includeArticleWide) {
            $criticisms[] = (new Criticism)
                ->setPurpose('structure')
                ->setConfidence(0.75)
                ->setImportance(0.7)
                ->setRemarks(['Improve overall flow between sections.']);
        }

        return $criticisms;
    }

    /**
     * @return list<Criticism>
     */
    private function collectCriticismsViaCritics(
        Article $article,
        ?SemanticContext $authorContext,
        ?SemanticContext $generalContext,
    ): array {
        $critics = $this->resolveCriticsForPipeline();
        if ($critics === null) {
            return [];
        }

        $this->info('Critics: '.implode(', ', array_map(
            static fn (Critic $critic): string => Str::afterLast($critic::class, '\\').' ('.$critic->getPurpose().')',
            $critics
        )));

        $criticisms = [];
        foreach ($critics as $critic) {
            $purpose = $critic->getPurpose();
            $batch = $this->timedCall(
                "criticizeArticle ({$purpose})",
                fn () => $critic->criticizeArticle($article, $authorContext, $generalContext)
            );
            $criticisms = array_merge($criticisms, $batch);
        }

        return $criticisms;
    }

    /**
     * @return list<Critic>|null
     */
    private function resolveCriticsForPipeline(): ?array
    {
        $synthesizerDriver = $this->option('synthesizer');
        if (is_string($synthesizerDriver) && $synthesizerDriver !== '') {
            return Synthesizer::driver($synthesizerDriver)->getCritics();
        }

        $mode = $this->choice(
            'Load critics for pipeline from',
            [
                'synthesizer' => 'Synthesizer driver (profile critics[])',
                'manager' => 'CriticManager (pick driver + purposes)',
            ],
            'synthesizer'
        );

        if (str_starts_with($mode, 'synthesizer')) {
            $drivers = array_keys(config('synthesizer.drivers', []));
            if ($drivers === []) {
                $this->error('No synthesizer drivers configured.');

                return null;
            }

            $defaultDriverIndex = array_search(config('synthesizer.default'), $drivers, true);
            $driverName = $this->choice(
                'Select synthesizer driver for critics',
                $drivers,
                $defaultDriverIndex === false ? 0 : $defaultDriverIndex
            );

            return Synthesizer::driver($driverName)->getCritics();
        }

        $manager = $this->laravel->make(CriticManager::class);
        $drivers = array_keys(config('synthesizer.critic.drivers', []));
        $driverName = $this->choice(
            'Critic driver',
            $drivers,
            array_search(config('synthesizer.critic.default', 'basic'), $drivers, true) ?: 0
        );

        if ($this->confirm('Run all critic purposes?', true)) {
            return $manager->getCritics($driverName);
        }

        $purpose = $this->choice('Critic purpose', $manager->purposes(), 0);

        return [$manager->makeCritic($purpose, $driverName)];
    }

    /**
     * @param  list<Criticism>  $criticisms
     */
    private function describeRectifyMode(array $criticisms): string
    {
        foreach ($criticisms as $criticism) {
            if (trim((string) ($criticism->getReference() ?? '')) === '') {
                return 'full article (markdown) — at least one criticism has no DOM reference';
            }
        }

        return 'targeted DOM fixes — every criticism has a reference';
    }

    private function displayArticleSummary(Article $article, string $label): void
    {
        $this->info($label);
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
     * @param  list<Criticism>  $criticisms
     */
    private function displayCriticisms(array $criticisms): void
    {
        $this->info('Criticisms: '.count($criticisms));

        if ($criticisms === []) {
            $this->line('(none)');
            $this->line('-----');

            return;
        }

        $rows = [];
        foreach ($criticisms as $index => $criticism) {
            $rows[] = [
                (string) ($index + 1),
                (string) ($criticism->getPurpose() ?? '—'),
                (string) ($criticism->getReference() ?? '—'),
                $criticism->getConfidence() !== null ? (string) $criticism->getConfidence() : '—',
                $criticism->getImportance() !== null ? (string) $criticism->getImportance() : '—',
                Str::limit(implode(' · ', $criticism->getRemarks()), 100),
            ];
        }

        $this->table(['#', 'purpose', 'reference', 'confidence', 'importance', 'remarks'], $rows);
        $this->line('-----');
    }

    private function displayDraft(Draft $draft): void
    {
        $article = $draft->getArticle() ?? new Article;

        $this->table(
            ['Field', 'Value'],
            [
                ['title', Str::limit((string) ($draft->getTitle() ?? ''), 120)],
                ['excerpt', Str::limit((string) ($draft->getExcerpt() ?? ''), 180)],
                ['article_children_count', (string) count($article->getChildren())],
            ]
        );

        $this->newLine();
        $this->comment('Draft JSON');
        $this->line($draft->toJson());

        $this->newLine();
        $this->comment('Article HTML');
        $this->line($article->toHtml());

        $this->newLine();
        $this->comment('Article tree');
        $this->renderArticleTree($article, 0);
        $this->line('-----');
    }

    private function displayRectifiedArticle(RectifiedArticle $rectified, Article $original): void
    {
        $article = $rectified->getArticle() ?? new Article;

        $this->table(
            ['Field', 'Value'],
            [
                ['rectifications_count', (string) count($rectified->getRectifications())],
                ['article_children_count', (string) count($article->getChildren())],
                ['html_changed', $article->toHtml() !== $original->toHtml() ? 'yes' : 'no'],
            ]
        );

        if ($rectified->getRectifications() !== []) {
            $this->newLine();
            $this->info('Rectifications applied');
            $rows = [];
            foreach ($rectified->getRectifications() as $rectification) {
                $rows[] = [
                    (string) ($rectification->getReference() ?? '—'),
                    $rectification->getConfidence() !== null ? (string) $rectification->getConfidence() : '—',
                    Str::limit(implode(' | ', $rectification->getAdjustments()), 120),
                ];
            }
            $this->table(['reference', 'confidence', 'adjustments'], $rows);
        }

        $this->newLine();
        $this->comment('RectifiedArticle JSON');
        $this->line($rectified->toJson());

        $this->newLine();
        $this->comment('Revised article HTML');
        $this->line($article->toHtml());

        $this->newLine();
        $this->comment('Revised article tree');
        $this->renderArticleTree($article, 0);
    }

    private function renderArticleTree(Element $element, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $tag = $element->getType()?->value ?? 'fragment';
        $identifier = trim($element->getIdentifier());
        $idSuffix = $identifier !== '' ? ' ['.$identifier.']' : '';
        $this->line(sprintf('%s<%s>%s', $indent, $tag, $idSuffix));

        foreach ($element->getChildren() as $child) {
            if (is_string($child)) {
                $this->line(sprintf('%s  "%s"', $indent, Str::limit($child, 120)));
                continue;
            }

            $this->renderArticleTree($child, $depth + 1);
        }
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
