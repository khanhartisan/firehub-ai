<?php

namespace App\Console\Commands\LiveTests\Synthesizer\Writer;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Contracts\Synthesizer\Writer\Draft;
use App\Contracts\Synthesizer\Writer\Writer;
use App\Facades\Synthesizer;
use App\Services\Synthesizer\Writer\WriterManager;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestWriterService extends Command
{
    protected $signature = 'live-test:synthesizer:test-writer-service';

    protected $description = 'Run a Writer in isolation (draft) or load writer from a synthesizer driver.';

    public function handle(): int
    {
        $resolution = $this->resolveWriter();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$writer, $sourceLabel] = $resolution;
        $brief = $this->buildBrief();
        $outline = $this->buildOutline();
        $generalContext = $this->buildSemanticContext();

        $this->newLine();
        $this->info('Writer: '.Str::afterLast($writer::class, '\\').' | '.$sourceLabel);
        $this->line('-----');

        try {
            $draft = $this->timedCall('draft', fn () => $writer->draft($brief, $outline, null, $generalContext));
            $this->displayDraft($draft);

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
     * @return array{0: Writer, 1: string}|null
     */
    private function resolveWriter(): ?array
    {
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
                "direct - WriterManager::driver('{$driverName}')",
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

        return [Synthesizer::driver($driverName)->getWriter(), "synthesizer driver «{$driverName}» - Writer"];
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

    private function buildSemanticContext(): SemanticContext
    {
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

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function timedCall(string $label, callable $callback): mixed
    {
        $start = microtime(true);
        $this->info("Calling {$label}...");
        $result = $callback();
        $this->info(sprintf('Processing time (%s): %.3f s', $label, microtime(true) - $start));
        $this->line('-----');

        return $result;
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
    }

    private function renderArticleTree(Element $element, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $tag = $element->getType()?->value ?? 'fragment';
        $this->line(sprintf('%s<%s>', $indent, $tag));

        foreach ($element->getChildren() as $child) {
            if (is_string($child)) {
                $this->line(sprintf('%s  "%s"', $indent, Str::limit($child, 120)));
                continue;
            }

            $this->renderArticleTree($child, $depth + 1);
        }
    }
}
