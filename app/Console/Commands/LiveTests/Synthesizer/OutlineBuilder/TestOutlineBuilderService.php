<?php

namespace App\Console\Commands\LiveTests\Synthesizer\OutlineBuilder;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Model\Article\StageData\ResearchStageData;
use App\Contracts\Synthesizer\BriefBuilder\Brief;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineBuilder;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Facades\Synthesizer;
use App\Services\Synthesizer\OutlineBuilder\Drivers\BasicOutlineBuilderDriver;
use App\Services\Synthesizer\OutlineBuilder\Drivers\OpenAIOutlineBuilderDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestOutlineBuilderService extends Command
{
    protected $signature = 'live-test:synthesizer:test-outline-builder-service';

    protected $description = 'Run an OutlineBuilder in isolation (outline) or load outline builder from a synthesizer driver.';

    public function handle(): int
    {
        $resolution = $this->resolveOutlineBuilder();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$outlineBuilder, $sourceLabel] = $resolution;

        $brief = $this->buildBrief();
        $context = $this->buildSemanticContext();

        $this->newLine();
        $this->info('OutlineBuilder: '.Str::afterLast($outlineBuilder::class, '\\').' | '.$sourceLabel);
        $this->line('-----');

        try {
            $outline = $this->timedCall('outline', fn () => $outlineBuilder->outline($brief, $context));
            $this->displayOutline($outline);

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
     * @return array{0: OutlineBuilder, 1: string}|null
     */
    private function resolveOutlineBuilder(): ?array
    {
        $mode = $this->choice(
            'How should the outline builder be loaded?',
            [
                'Direct: construct outline builder only (no Synthesizer)',
                'From synthesizer driver: OutlineBuilder (full wiring)',
            ],
            0
        );

        if (str_starts_with($mode, 'Direct')) {
            $impl = $this->choice(
                'Which implementation?',
                [
                    'BasicOutlineBuilderDriver — deterministic/local',
                    'OpenAIOutlineBuilderDriver — OpenAI Responses API (uses synthesizer.outline_builder.drivers.openai config)',
                ],
                1
            );

            if (str_contains($impl, 'Basic')) {
                return [new BasicOutlineBuilderDriver, 'direct · BasicOutlineBuilderDriver'];
            }

            return [
                $this->laravel->make(OpenAIOutlineBuilderDriver::class),
                'direct · OpenAIOutlineBuilderDriver (container)',
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

        $outlineBuilder = Synthesizer::driver($driverName)->getOutlineBuilder();

        return [$outlineBuilder, "synthesizer driver «{$driverName}» · OutlineBuilder"];
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

        $defaultOutlineFocus = 'Include a section about trade-offs and implementation risks.';

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
            'outline_focus',
            'Additional outline-specific requirements.',
            (string) $this->ask('Outline focus', $defaultOutlineFocus)
        );
        // Mirror HandleOutlineStage: context receives getResearchStageData()->getPoints()
        $context->set(
            'researched_points',
            'A list of researched points that related to the given idea',
            $this->makeResearchStageDataFixture()->getPoints()
        );

        return $context;
    }

    /**
     * @return list<RelevantPoint>
     */
    private function makeFixturePoints(): array
    {
        return [
            (new RelevantPoint)
                ->setHeadline('AI onboarding assistants reduce setup friction')
                ->setDescription('Teams report faster first-value moments when AI guides setup with contextual steps.')
                ->setEvidences(['Median setup completion time dropped 24% in pilot cohorts.'])
                ->setRationale('Supports activation-focused editorial angle.')
                ->setRelevance(0.9),
            (new RelevantPoint)
                ->setHeadline('Uncontrolled rollout can increase support burden')
                ->setDescription('Without guardrails, users may receive inconsistent recommendations and file more tickets.')
                ->setEvidences(['Support volume rose 11% in one ungoverned rollout cohort.'])
                ->setRationale('Adds risk-management perspective and balance.')
                ->setRelevance(0.78),
            (new RelevantPoint)
                ->setHeadline('Some domain-specific governance points are too technical for this article')
                ->setDescription('Deep legal implementation details may be out of scope for a practical onboarding playbook.')
                ->setEvidences(['Legal checklist details are useful only when discussing compliance implementation.'])
                ->setRationale('Gives the model a potentially droppable point to test filtering behavior.')
                ->setRelevance(0.42),
        ];
    }

    private function makeResearchStageDataFixture(): ResearchStageData
    {
        return (new ResearchStageData)
            ->setPoints($this->makeFixturePoints());
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

    private function displayOutline(Outline $outline): void
    {
        $this->line($outline->toJson());
        $this->newLine();
        $this->info('Outline result');
        $this->table(
            ['Field', 'Value'],
            [
                ['title', Str::limit((string) ($outline->getTitle() ?? ''), 120)],
                ['items_count', (string) count($outline->getItems())],
            ]
        );

        if ($outline->getItems() === []) {
            $this->warn('No outline items returned.');

            return;
        }

        $this->newLine();
        $this->comment('Outline tree');
        $this->renderOutlineItems($outline->getItems(), 0);
    }

    /**
     * @param  list<OutlineItem>  $items
     */
    private function renderOutlineItems(array $items, int $depth): void
    {
        foreach ($items as $index => $item) {
            $indent = str_repeat('  ', $depth);
            $point = $item->getPoint();
            $heading = trim((string) ($point->getHeadline() ?? ''));
            $this->line(sprintf(
                '%s%d) %s',
                $indent,
                $index + 1,
                $heading !== '' ? $heading : '(no heading)'
            ));

            $brief = trim((string) ($point->getDescription() ?? ''));
            if ($brief !== '') {
                $this->line($indent.'   Brief: '.Str::limit($brief, 240));
            }

            $guidelines = $item->getGuidelines();
            foreach ($guidelines as $instructionIndex => $instruction) {
                $this->line(sprintf(
                    '%s   - Guideline %d: %s',
                    $indent,
                    $instructionIndex + 1,
                    Str::limit($instruction, 260)
                ));
            }

            foreach ($point->getEvidences() as $evidenceIndex => $evidence) {
                $this->line(sprintf(
                    '%s   - Evidence %d: %s',
                    $indent,
                    $evidenceIndex + 1,
                    Str::limit($evidence, 260)
                ));
            }

            $subItems = $item->getSubItems();
            if ($subItems !== []) {
                $this->renderOutlineItems($subItems, $depth + 1);
            }
        }
    }
}
