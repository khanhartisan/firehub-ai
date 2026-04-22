<?php

namespace App\Console\Commands\LiveTests\Synthesizer\Researcher;

use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\IdeaPoint;
use App\Contracts\Synthesizer\Researcher\Researcher;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Facades\Synthesizer;
use App\Services\Synthesizer\Researcher\Drivers\BasicResearcherDriver;
use App\Services\Synthesizer\Researcher\Drivers\OpenAIResearcherDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestResearcherService extends Command
{
    protected $signature = 'live-test:synthesizer:test-researcher-service';

    protected $description = 'Run a Researcher in isolation (extractPoints) or load the researcher from a synthesizer driver.';

    public function handle(): int
    {
        $resolution = $this->resolveResearcher();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$researcher, $sourceLabel] = $resolution;

        $title = (string) $this->ask('Idea title', 'AI copilots: adoption patterns in product teams');
        $description = (string) $this->ask(
            'Idea description',
            'Practical analysis of adoption, governance, and ROI trade-offs.'
        );
        $content = (string) $this->ask('Input content', $this->defaultContent());

        $idea = $this->makeIdea($title, $description);

        $this->newLine();
        $this->info('Researcher: '.Str::afterLast($researcher::class, '\\').' | '.$sourceLabel);
        $this->line('-----');

        try {
            $points = $this->timedCall('extractPoints', fn () => $researcher->extractPoints($idea, $content));
            $this->displayIdeaPoints($points->getIdeaPoints());

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
     * @return array{0: Researcher, 1: string}|null
     */
    private function resolveResearcher(): ?array
    {
        $mode = $this->choice(
            'How should the researcher be loaded?',
            [
                'Direct: construct researcher only (no Synthesizer)',
                'From synthesizer driver: Researcher (full wiring)',
            ],
            0
        );

        if (str_starts_with($mode, 'Direct')) {
            $impl = $this->choice(
                'Which implementation?',
                [
                    'BasicResearcherDriver — local deterministic extraction',
                    'OpenAIResearcherDriver — OpenAI Responses API (uses synthesizer.openai_researcher config)',
                ],
                0
            );

            if (str_contains($impl, 'Basic')) {
                return [new BasicResearcherDriver, 'direct · BasicResearcherDriver'];
            }

            return [
                $this->laravel->make(OpenAIResearcherDriver::class),
                'direct · OpenAIResearcherDriver (container)',
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

        $researcher = Synthesizer::driver($driverName)->getResearcher();

        return [$researcher, "synthesizer driver «{$driverName}» · Researcher"];
    }

    private function makeIdea(string $title, string $description): Idea
    {
        $intent = (new Intent)
            ->setTitle($title)
            ->setDescription($description)
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::TOPICAL)
            ->setTypes([IntentType::INFORMATIONAL]);

        return new Idea($intent, 0.7, 'Live test fixture idea');
    }

    private function defaultContent(): string
    {
        return <<<'TEXT'
Northwind Labs surveyed 214 software teams across North America and Europe between Jan-Mar 2026. 68% reported they now use at least one AI copilot weekly, up from 41% in Q2 2025. Median time-to-first-prototype dropped from 11 days to 7 days after rollout.

The same survey reports uneven ROI by team size. Teams with fewer than 20 engineers estimated a 1.9x productivity gain, while teams with more than 100 engineers reported only 1.2x due to process overhead and review bottlenecks.

Cost pressure remains a key blocker. Finance leaders cited monthly spend increases from $18k to $46k after expanding access org-wide. 57% of respondents introduced hard usage caps, and 34% required manager approval for "high-context" model tiers.

Quality outcomes improved in some areas but regressed in others. Bug reopen rates fell 12% for teams using AI-assisted test generation, yet incident postmortems noted a 23% increase in "hallucinated dependency assumptions" in architecture docs.

Security and compliance teams moved earlier in the buying cycle. In 2024, legal/security review typically happened after pilot completion; in 2026, 72% of buyers required data retention, training-data opt-out, and regional processing terms before pilot approval.

An engineering director at a fintech company said, "We kept the assistant because onboarding got faster, but we had to create a red-team prompt review checklist after two high-risk suggestions slipped into production code review."

Vendor lock-in concerns are rising. 49% of respondents said they now mandate exportable prompt logs and model-agnostic workflow tooling. Procurement teams increasingly score vendors on migration readiness, not just benchmark quality.

Despite concerns, intent to expand remains high: 61% of teams plan to broaden AI assistant access in the next two quarters, but most pair expansion with stricter governance controls and evidence-based usage policies.
TEXT;
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
     * @param  list<IdeaPoint>  $ideaPoints
     */
    private function displayIdeaPoints(array $ideaPoints): void
    {
        if ($ideaPoints === []) {
            $this->warn('No idea points returned.');

            return;
        }

        $this->table(
            ['#', 'headline', 'relevance', 'evidences'],
            array_map(
                static fn (IdeaPoint $item, int $index): array => [
                    (string) ($index + 1),
                    Str::limit((string) ($item->getPoint()->getHeadline() ?? ''), 72),
                    $item->getRelevance() !== null ? (string) $item->getRelevance() : '—',
                    (string) count($item->getPoint()->getEvidences()),
                ],
                $ideaPoints,
                array_keys($ideaPoints)
            )
        );

        foreach ($ideaPoints as $index => $item) {
            $this->newLine();
            $this->comment('Point '.($index + 1).': '.($item->getPoint()->getHeadline() ?? '(no headline)'));
            $this->line(Str::limit((string) ($item->getPoint()->getDescription() ?? ''), 800));

            foreach ($item->getPoint()->getEvidences() as $i => $evidence) {
                $this->line('- Evidence '.($i+1).': '.$evidence);
            }
        }
    }
}
