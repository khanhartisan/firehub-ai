<?php

namespace App\Console\Commands\LiveTests\Synthesizer\Editor;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Model\Author\AuthorContexts\LinguisticContext;
use App\Contracts\Synthesizer\Editor\Editor;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\OutlineBuilder\Outline;
use App\Contracts\Synthesizer\OutlineBuilder\OutlineItem;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Facades\Synthesizer;
use App\Services\Synthesizer\Editor\Drivers\BasicEditorDriver;
use App\Services\Synthesizer\Editor\Drivers\OpenAIEditorDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestEditorService extends Command
{
    protected $signature = 'live-test:synthesizer:test-editor-service';

    protected $description = 'Run an Editor in isolation (determineAuthorContext, tailorOutlineForAuthor, distillAuthorContextForOutlineItem) or load editor from a synthesizer driver.';

    public function handle(): int
    {
        $resolution = $this->resolveEditor();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$editor, $sourceLabel] = $resolution;

        $idea = $this->buildIdea();
        $authorContexts = $this->makeAuthorContextFixtures();
        $outline = $this->makeOutlineFixture();
        $generalContext = $this->buildGeneralContext();

        $action = $this->choice(
            'What to run',
            [
                'determine_author_context',
                'tailor_outline_for_author',
                'distill_author_context_for_outline_item',
                'full_pipeline',
            ],
            2
        );

        $this->newLine();
        $this->info('Editor: '.Str::afterLast($editor::class, '\\').' | '.$sourceLabel);
        $this->displayIdeaSummary($idea);
        $this->displayAuthorContextCandidates($authorContexts);
        $this->displayOutlineSummary($outline);
        $this->line('-----');

        try {
            if ($action === 'determine_author_context') {
                return $this->runDetermineAuthorContext($editor, $idea, $authorContexts);
            }

            if ($action === 'tailor_outline_for_author') {
                return $this->runTailorOutlineForAuthor(
                    $editor,
                    $outline,
                    $this->pickAuthorContext($authorContexts)
                );
            }

            if ($action === 'distill_author_context_for_outline_item') {
                return $this->runDistillAuthorContextForOutlineItem(
                    $editor,
                    $outline,
                    $this->pickAuthorContext($authorContexts),
                    $generalContext
                );
            }

            $picked = $this->timedCall(
                'determineAuthorContext',
                fn () => $editor->determineAuthorContext($idea, $authorContexts)
            );
            $this->displaySemanticContext('Picked author context', $picked);

            $tailoredOutline = $this->timedCall(
                'tailorOutlineForAuthor',
                fn () => $editor->tailorOutlineForAuthor($outline, $picked)
            );
            $this->displayOutlineSummary($tailoredOutline);

            return $this->runDistillAuthorContextForOutlineItem($editor, $tailoredOutline, $picked, $generalContext, false);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * @return array{0: Editor, 1: string}|null
     */
    private function resolveEditor(): ?array
    {
        $mode = $this->choice(
            'How should the editor be loaded?',
            [
                'Direct: construct editor only (no Synthesizer)',
                'From synthesizer driver: Editor (full wiring)',
            ],
            0
        );

        if (str_starts_with($mode, 'Direct')) {
            $impl = $this->choice(
                'Which implementation?',
                [
                    'BasicEditorDriver — deterministic overlap scoring',
                    'OpenAIEditorDriver — OpenAI Responses API (uses synthesizer.editor.drivers.openai config)',
                ],
                0
            );

            if (str_contains($impl, 'Basic')) {
                return [new BasicEditorDriver, 'direct · BasicEditorDriver'];
            }

            return [
                $this->laravel->make(OpenAIEditorDriver::class),
                'direct · OpenAIEditorDriver (container)',
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

        return [
            Synthesizer::driver($driverName)->getEditor(),
            "synthesizer driver «{$driverName}» · Editor",
        ];
    }

    private function buildIdea(): Idea
    {
        $title = (string) $this->ask('Idea title', 'SaaS onboarding playbook');
        $description = (string) $this->ask(
            'Idea description',
            'How teams improve activation with practical onboarding experiments.'
        );
        $reason = (string) $this->ask(
            'Idea reason',
            'Practical SaaS onboarding playbook for operators'
        );

        $intent = (new Intent)
            ->setTitle($title)
            ->setDescription($description)
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::EVERGREEN)
            ->setTypes([IntentType::INFORMATIONAL]);

        return new Idea($intent, 0.8, $reason);
    }

    /**
     * @return list<SemanticContext>
     */
    private function makeAuthorContextFixtures(): array
    {
        return [
            (new SemanticContext)->set(
                'voice',
                'Author voice',
                'Formal academic commentary on macroeconomics'
            ),
            (new AuthorContext)
                ->set('voice', 'Author voice', 'Practical SaaS onboarding guidance for operators')
                ->setLinguisticContext(
                    (new LinguisticContext)->setVocabularyTier('Colloquial', 2.0)
                ),
        ];
    }

    private function makeOutlineFixture(): Outline
    {
        $item = (new OutlineItem)->setPoint(
            (new RelevantPoint)
                ->setHeadline('Activation tactics')
                ->setDescription('Explain onboarding experiments with measurable outcomes.')
        );
        $item->setGuidelines(['Use concrete metrics.', 'Lead with operator pain, then metrics.']);

        return (new Outline)
            ->setTitle('SaaS onboarding playbook outline')
            ->setItems([$item]);
    }

    private function buildGeneralContext(): SemanticContext
    {
        return (new SemanticContext)->set(
            'outline_focus',
            'Additional outline focus.',
            (string) $this->ask('Outline focus (general context)', 'Emphasize trade-offs and implementation risks')
        );
    }

    /**
     * @param  list<SemanticContext>  $authorContexts
     */
    private function runDetermineAuthorContext(Editor $editor, Idea $idea, array $authorContexts): int
    {
        $picked = $this->timedCall(
            'determineAuthorContext',
            fn () => $editor->determineAuthorContext($idea, $authorContexts)
        );
        $this->displaySemanticContext('Picked author context', $picked);

        return self::SUCCESS;
    }

    /**
     * @param  list<SemanticContext>  $authorContexts
     */
    private function pickAuthorContext(array $authorContexts): SemanticContext
    {
        $labels = [];
        foreach ($authorContexts as $index => $context) {
            $voice = $context->getVoiceValue();
            $labels[] = sprintf(
                '#%d — %s',
                $index + 1,
                $voice !== null && $voice !== ''
                    ? Str::limit((string) $voice, 72)
                    : Str::limit(json_encode($context->toArray(), JSON_UNESCAPED_UNICODE) ?: '{}', 72)
            );
        }

        $pickedLabel = $this->choice('Author context for distillation', $labels, 1);
        $index = array_search($pickedLabel, $labels, true);

        return $authorContexts[$index === false ? 0 : $index];
    }

    private function runTailorOutlineForAuthor(
        Editor $editor,
        Outline $outline,
        SemanticContext $authorContext,
        bool $timed = true
    ): int {
        $tailor = fn () => $editor->tailorOutlineForAuthor($outline, $authorContext);

        $tailored = $timed
            ? $this->timedCall('tailorOutlineForAuthor', $tailor)
            : $tailor();

        $this->displayOutlineSummary($tailored);

        return self::SUCCESS;
    }

    private function runDistillAuthorContextForOutlineItem(
        Editor $editor,
        Outline $outline,
        SemanticContext $authorContext,
        SemanticContext $generalContext,
        bool $timed = true
    ): int {
        $outlineItemId = $this->pickOutlineItemId($outline);

        $distill = fn () => $editor->distillAuthorContextForOutlineItem(
            $outline,
            $outlineItemId,
            $authorContext,
            $generalContext
        );

        $distilled = $timed
            ? $this->timedCall('distillAuthorContextForOutlineItem', $distill)
            : $distill();

        $this->displaySemanticContext('Distilled author context', $distilled);

        return self::SUCCESS;
    }

    private function pickOutlineItemId(Outline $outline): string
    {
        $items = $this->flattenOutlineItems($outline->getItems());
        if ($items === []) {
            throw new \InvalidArgumentException('Outline has no items.');
        }

        if (count($items) === 1) {
            return $items[0]->getIdentifier();
        }

        $labels = [];
        foreach ($items as $index => $item) {
            $headline = trim((string) ($item->getPoint()->getHeadline() ?? ''));
            $labels[] = sprintf(
                '#%d — %s (%s)',
                $index + 1,
                $headline !== '' ? Str::limit($headline, 56) : '(no headline)',
                $item->getIdentifier()
            );
        }

        $pickedLabel = $this->choice('Outline item to distill for', $labels, 0);
        $index = array_search($pickedLabel, $labels, true);

        return $items[$index === false ? 0 : $index]->getIdentifier();
    }

    /**
     * @param  list<OutlineItem>  $items
     * @return list<OutlineItem>
     */
    private function flattenOutlineItems(array $items): array
    {
        $flat = [];
        foreach ($items as $item) {
            $flat[] = $item;
            foreach ($this->flattenOutlineItems($item->getSubItems()) as $subItem) {
                $flat[] = $subItem;
            }
        }

        return $flat;
    }

    private function displayIdeaSummary(Idea $idea): void
    {
        $intent = $idea->getIntent();
        $this->info('Idea fixture');
        $this->table(
            ['Field', 'Value'],
            [
                ['title', Str::limit((string) ($intent->getTitle() ?? ''), 120)],
                ['description', Str::limit((string) ($intent->getDescription() ?? ''), 120)],
                ['reason', Str::limit((string) ($idea->getReason() ?? ''), 120)],
            ]
        );
    }

    /**
     * @param  list<SemanticContext>  $contexts
     */
    private function displayAuthorContextCandidates(array $contexts): void
    {
        $this->info('Author context candidates');
        $rows = [];
        foreach ($contexts as $index => $context) {
            $identifier = method_exists($context, 'getIdentifier')
                ? (string) $context->getIdentifier()
                : '—';
            $voice = $context->getVoiceValue();
            $rows[] = [
                (string) ($index + 1),
                $identifier,
                $voice !== null && $voice !== ''
                    ? Str::limit((string) $voice, 80)
                    : Str::limit(json_encode($context->toArray(), JSON_UNESCAPED_UNICODE) ?: '{}', 80),
            ];
        }
        $this->table(['#', 'identifier', 'voice / summary'], $rows);
    }

    private function displayOutlineSummary(Outline $outline): void
    {
        $this->info('Outline fixture');
        $this->table(
            ['Field', 'Value'],
            [
                ['title', Str::limit((string) ($outline->getTitle() ?? ''), 120)],
                ['items_count', (string) count($outline->getItems())],
            ]
        );

        $items = $this->flattenOutlineItems($outline->getItems());
        if ($items === []) {
            return;
        }

        $this->table(
            ['#', 'outline_item_id', 'headline'],
            array_map(
                static fn (OutlineItem $item, int $i): array => [
                    (string) ($i + 1),
                    $item->getIdentifier(),
                    Str::limit((string) ($item->getPoint()->getHeadline() ?? ''), 72),
                ],
                $items,
                array_keys($items)
            )
        );
    }

    private function displaySemanticContext(string $label, SemanticContext $context): void
    {
        $this->info($label);
        $this->line($context->toJson());
        $this->newLine();

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
                Str::limit((string) ($entry['description'] ?? ''), 48),
                Str::limit((string) ($value ?? ''), 80),
                isset($entry['weight']) && is_numeric($entry['weight'])
                    ? (string) $entry['weight']
                    : '—',
            ];
        }

        if ($rows === []) {
            $this->warn('Context has no entries.');

            return;
        }

        $this->table(['key', 'description', 'value', 'weight'], $rows);
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
