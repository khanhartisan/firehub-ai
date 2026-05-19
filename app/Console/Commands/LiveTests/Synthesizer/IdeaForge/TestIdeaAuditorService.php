<?php

namespace App\Console\Commands\LiveTests\Synthesizer\IdeaForge;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditor;
use App\Contracts\Synthesizer\IdeaForge\IdeaAuditReport;
use App\Contracts\Synthesizer\IdeaForge\IdeaUniquenessReport;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Facades\Synthesizer;
use App\Models\Article;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\BasicIdeaAuditorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAuditor\Drivers\OpenAIIdeaAuditorDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestIdeaAuditorService extends Command
{
    protected $signature = 'live-test:synthesizer:test-idea-auditor-service';

    protected $description = 'Run an IdeaAuditor in isolation (isIdeaUnique, audit) or load the auditor from a synthesizer driver’s IdeaForge.';

    public function handle(): int
    {
        $resolution = $this->resolveIdeaAuditor();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$auditor, $sourceLabel] = $resolution;

        $clientId = (string) $this->ask('Client id (for uniqueness baseline / DB)', 'live-test-client');

        $title = (string) $this->ask('Idea title', 'Q4 pricing benchmarks for AI developer tools');
        $description = (string) $this->ask(
            'Idea description',
            'Compare list prices and common discount patterns; help readers decide when to buy or wait.'
        );

        $idea = $this->makeIdea($title, $description);

        $action = $this->choice(
            'What to run',
            [
                'is_idea_unique',
                'audit',
                'both',
            ],
            2
        );

        $this->newLine();
        $this->info('Auditor: '.Str::afterLast($auditor::class, '\\').' | '.$sourceLabel);
        $this->comment('Idea identifier: '.$idea->getIdentifier());
        $this->line('-----');

        try {
            if ($action === 'is_idea_unique') {
                return $this->runIsIdeaUnique($auditor, $clientId, $idea);
            }

            if ($action === 'audit') {
                return $this->runAudit($auditor, $idea);
            }

            $this->runIsIdeaUnique($auditor, $clientId, $idea);
            $this->newLine();

            return $this->runAudit($auditor, $idea);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * @return array{0: IdeaAuditor, 1: string}|null
     */
    private function resolveIdeaAuditor(): ?array
    {
        $mode = $this->choice(
            'How should the idea auditor be loaded?',
            [
                'Direct: construct auditor only (no Synthesizer)',
                'From synthesizer driver: IdeaForge auditor (full wiring)',
            ],
            0
        );

        if (str_starts_with($mode, 'Direct')) {
            $impl = $this->choice(
                'Which implementation?',
                [
                    'BasicIdeaAuditorDriver — stub uniqueness (tests / local; no vector)',
                    'OpenAIIdeaAuditorDriver — OpenAI Responses API (uses synthesizer.idea_auditor.drivers.openai config)',
                ],
                0
            );

            if (str_contains($impl, 'Basic')) {
                return [new BasicIdeaAuditorDriver, 'direct · BasicIdeaAuditorDriver'];
            }

            return [
                $this->laravel->make(OpenAIIdeaAuditorDriver::class),
                'direct · OpenAIIdeaAuditorDriver (container)',
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

        $auditor = Synthesizer::driver($driverName)->getIdeaForge()->getIdeaAuditor();

        return [$auditor, "synthesizer driver «{$driverName}» · IdeaForge"];
    }

    protected function makeIdea(string $title, string $description): Idea
    {
        $intent = (new Intent)
            ->setTitle($title)
            ->setDescription($description)
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::TOPICAL)
            ->setTypes([IntentType::INFORMATIONAL]);

        return new Idea($intent, 0.72, 'Live test fixture idea');
    }

    private function runIsIdeaUnique(IdeaAuditor $auditor, string $clientId, Idea $idea): int
    {
        $report = $this->timedCall('isIdeaUnique', fn () => $auditor->isIdeaUnique($clientId, $idea));
        $this->displayUniquenessReport($report);

        return self::SUCCESS;
    }

    private function runAudit(IdeaAuditor $auditor, Idea $idea): int
    {
        $report = $this->timedCall('audit', fn () => $auditor->audit($idea));
        $this->displayAuditReport($report);

        return self::SUCCESS;
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

    private function displayUniquenessReport(IdeaUniquenessReport $report): void
    {
        $this->info('Uniqueness report');

        $similar = $report->getSimilarArticles();
        $similarPreview = $similar === []
            ? '—'
            : implode('; ', array_map(static function (Article $a): string {
                return (string) $a->title;
            }, array_slice($similar, 0, 5))).(count($similar) > 5 ? ' …' : '');

        $this->table(
            ['Field', 'Value'],
            [
                ['client_id', $report->getClientId() ?? '—'],
                ['idea_identifier', $report->getIdeaIdentifier() ?? '—'],
                ['similarity', $report->getSimilarity() !== null ? (string) $report->getSimilarity() : '—'],
                ['is_unique', $report->getIsUnique() === null ? '—' : ($report->getIsUnique() ? 'true' : 'false')],
                ['similar_articles_count', (string) count($similar)],
                ['similar_article_titles (preview)', $similarPreview],
            ]
        );
        $this->line('-----');
    }

    private function displayAuditReport(IdeaAuditReport $report): void
    {
        $this->info('Audit report');

        $this->table(
            ['Field', 'Value'],
            [
                ['score', $report->getScore() !== null ? (string) $report->getScore() : '—'],
                ['idea_title', Str::limit((string) ($report->getIdea()->getIntent()->getTitle() ?? ''), 72)],
            ]
        );

        $highlights = $report->getHighlights();
        if ($highlights !== []) {
            $this->comment('Highlights');
            foreach ($highlights as $i => $line) {
                $this->line('  '.($i + 1).'. '.$line);
            }
        } else {
            $this->warn('No highlights.');
        }

        $concerns = $report->getConcerns();
        if ($concerns !== []) {
            $this->comment('Concerns');
            foreach ($concerns as $i => $line) {
                $this->line('  '.($i + 1).'. '.$line);
            }
        } else {
            $this->comment('Concerns: (none)');
        }

        $this->line('-----');
    }
}
