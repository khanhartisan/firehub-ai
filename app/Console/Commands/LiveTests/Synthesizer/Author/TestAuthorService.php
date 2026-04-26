<?php

namespace App\Console\Commands\LiveTests\Synthesizer\Author;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Synthesizer\Author\Author;
use App\Contracts\Synthesizer\Author\Draft;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Facades\Synthesizer;
use App\Services\Synthesizer\Author\Drivers\BasicAuthorDriver;
use App\Services\Synthesizer\Author\Drivers\OpenAIAuthorDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestAuthorService extends Command
{
    protected $signature = 'live-test:synthesizer:test-author-service';

    protected $description = 'Run an Author in isolation (draft) or load author from a synthesizer driver.';

    public function handle(): int
    {
        $resolution = $this->resolveAuthor();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$author, $sourceLabel] = $resolution;
        $brief = $this->buildBrief();
        $outline = $this->buildOutline();
        $context = $this->buildSemanticContext();

        $this->newLine();
        $this->info('Author: '.Str::afterLast($author::class, '\\').' | '.$sourceLabel);
        $this->line('-----');

        try {
            $draft = $this->timedCall('draft', fn () => $author->draft($brief, $outline, $context));
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
     * @return array{0: Author, 1: string}|null
     */
    private function resolveAuthor(): ?array
    {
        $mode = $this->choice(
            'How should the author be loaded?',
            [
                'Direct: construct author only (no Synthesizer)',
                'From synthesizer driver: Author (full wiring)',
            ],
            0
        );

        if (str_starts_with($mode, 'Direct')) {
            $impl = $this->choice(
                'Which implementation?',
                [
                    'BasicAuthorDriver — deterministic/local',
                    'OpenAIAuthorDriver — OpenAI Responses API (uses synthesizer.openai_author config)',
                ],
                1
            );

            if (str_contains($impl, 'Basic')) {
                return [new BasicAuthorDriver, 'direct · BasicAuthorDriver'];
            }

            return [
                $this->laravel->make(OpenAIAuthorDriver::class),
                'direct · OpenAIAuthorDriver (container)',
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

        return [Synthesizer::driver($driverName)->getAuthor(), "synthesizer driver «{$driverName}» · Author"];
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

        $defaultAuthorFocus = 'Keep examples concrete and include one compact checklist.';

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
            'author_focus',
            'Additional authoring requirements.',
            (string) $this->ask('Author focus', $defaultAuthorFocus)
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
        $this->info("Calling {$label}…");
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
