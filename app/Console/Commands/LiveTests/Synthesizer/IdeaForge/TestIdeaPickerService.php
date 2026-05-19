<?php

namespace App\Console\Commands\LiveTests\Synthesizer\IdeaForge;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IdeaPicker;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Facades\Synthesizer;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\BasicIdeaPickerDriver;
use App\Services\Synthesizer\IdeaForge\IdeaPicker\Drivers\OpenAIIdeaPickerDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestIdeaPickerService extends Command
{
    protected $signature = 'live-test:synthesizer:test-idea-picker-service';

    protected $description = 'Run an IdeaPicker in isolation (pick) or load the picker from a synthesizer driver\'s IdeaForge.';

    public function handle(): int
    {
        $resolution = $this->resolveIdeaPicker();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$picker, $sourceLabel] = $resolution;

        $defaultContext = <<<'CTX'
A practical B2B editorial stream for SaaS operators: product launches, pricing moves, hiring signals, and tactical playbooks.
CTX;
        $context = (new SemanticContext)->set(
            'article_context',
            'Editorial / business context',
            (string) $this->ask('Editorial / business context', $defaultContext)
        );
        $limit = max(1, min(10, (int) $this->ask('Pick limit', '2')));
        $reports = $this->makeFixtureReports();

        $this->newLine();
        $this->info('Picker: '.Str::afterLast($picker::class, '\\').' | '.$sourceLabel);
        $this->info('Fixture reports');
        $this->table(
            ['#', 'audit_report_id', 'title', 'score'],
            array_map(
                static fn (IdeaAuditReport $report, int $i): array => [
                    (string) ($i + 1),
                    (string) $report->getIdentifier(),
                    Str::limit((string) ($report->getIdea()->getIntent()->getTitle() ?? ''), 72),
                    $report->getScore() !== null ? (string) $report->getScore() : '—',
                ],
                $reports,
                array_keys($reports)
            )
        );
        $this->line('-----');

        try {
            $picked = $this->timedCall('pick', fn () => $picker->pick($reports, $context, $limit));
            $this->displayPickedReports($picked);

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
     * @return array{0: IdeaPicker, 1: string}|null
     */
    private function resolveIdeaPicker(): ?array
    {
        $mode = $this->choice(
            'How should the idea picker be loaded?',
            [
                'Direct: construct picker only (no Synthesizer)',
                'From synthesizer driver: IdeaForge picker (full wiring)',
            ],
            0
        );

        if (str_starts_with($mode, 'Direct')) {
            $impl = $this->choice(
                'Which implementation?',
                [
                    'BasicIdeaPickerDriver — deterministic score sort',
                    'OpenAIIdeaPickerDriver — OpenAI Responses API (uses synthesizer.idea_picker.drivers.openai config)',
                ],
                0
            );

            if (str_contains($impl, 'Basic')) {
                return [new BasicIdeaPickerDriver, 'direct · BasicIdeaPickerDriver'];
            }

            return [
                $this->laravel->make(OpenAIIdeaPickerDriver::class),
                'direct · OpenAIIdeaPickerDriver (container)',
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

        $picker = Synthesizer::driver($driverName)->getIdeaForge()->getIdeaPicker();

        return [$picker, "synthesizer driver «{$driverName}» · IdeaForge"];
    }

    /**
     * @return list<IdeaAuditReport>
     */
    private function makeFixtureReports(): array
    {
        return [
            new IdeaAuditReport(
                $this->makeIdea(
                    'Q4 pricing benchmark for AI developer tools',
                    'Compare list prices, common discount windows, and contract gotchas by segment.'
                ),
                0.90,
                ['Strong business relevance', 'Actionable comparison angle'],
                []
            ),
            new IdeaAuditReport(
                $this->makeIdea(
                    'How to pick an AI coding assistant in 2026',
                    'Framework for selecting tools by team size and governance constraints.'
                ),
                0.70,
                ['Useful framework for buyers'],
                ['Broad topic; needs sharper differentiation']
            ),
            new IdeaAuditReport(
                $this->makeIdea(
                    'Weekly AI news roundup',
                    'General roundup of AI product updates from the past week.'
                ),
                0.45,
                ['Easy to produce'],
                ['Low uniqueness and weak strategic angle']
            ),
        ];
    }

    private function makeIdea(string $title, string $description): Idea
    {
        $intent = (new Intent)
            ->setTitle($title)
            ->setDescription($description)
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::TOPICAL)
            ->setTypes([IntentType::INFORMATIONAL]);

        return new Idea($intent, 0.72, 'Live test fixture idea');
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

    /**
     * @param  list<IdeaAuditReport>|null  $picked
     */
    private function displayPickedReports(?array $picked): void
    {
        if ($picked === null || $picked === []) {
            $this->warn('No ideas picked (null / empty).');

            return;
        }

        $this->info('Picked reports');
        $this->table(
            ['#', 'audit_report_id', 'title', 'score'],
            array_map(
                static fn (IdeaAuditReport $report, int $i): array => [
                    (string) ($i + 1),
                    (string) $report->getIdentifier(),
                    Str::limit((string) ($report->getIdea()->getIntent()->getTitle() ?? ''), 72),
                    $report->getScore() !== null ? (string) $report->getScore() : '—',
                ],
                $picked,
                array_keys($picked)
            )
        );
        $this->line('-----');
    }
}
