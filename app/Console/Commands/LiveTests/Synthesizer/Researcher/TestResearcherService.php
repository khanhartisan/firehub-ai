<?php

namespace App\Console\Commands\LiveTests\Synthesizer\Researcher;

use App\Contracts\CommonData\Fact;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\ConsolidationResult;
use App\Contracts\Synthesizer\Researcher\ConflictedPoints;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
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

    protected $description = 'Run a Researcher in isolation (extractIdeaPoints) or load the researcher from a synthesizer driver.';

    public function handle(): int
    {
        $resolution = $this->resolveResearcher();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$researcher, $sourceLabel] = $resolution;
        $mode = $this->choice(
            'Which researcher function do you want to test?',
            [
                'extractIdeaPoints',
                'consolidateIdeaPoints (simulated input)',
                'resolveIdeaConflictedPointsByFacts (simulated conflict + facts)',
            ],
            0
        );

        $title = (string) $this->ask('Idea title', 'AI copilots: adoption patterns in product teams');
        $description = (string) $this->ask(
            'Idea description',
            'Practical analysis of adoption, governance, and ROI trade-offs.'
        );

        $idea = $this->makeIdea($title, $description);

        $this->newLine();
        $this->info('Researcher: '.Str::afterLast($researcher::class, '\\').' | '.$sourceLabel);
        $this->line('-----');

        try {
            if (str_starts_with($mode, 'extractIdeaPoints')) {
                $content = (string) $this->ask('Input content', $this->defaultContent());
                $points = $this->timedCall('extractIdeaPoints', fn () => $researcher->extractIdeaPoints($idea, $content));
                $this->displayPoints($points);
            } elseif (str_starts_with($mode, 'consolidateIdeaPoints')) {
                $simulatedPoints = $this->makeSimulatedRelevantPoints();
                $this->info(sprintf('Using %d simulated relevant points for consolidation.', count($simulatedPoints)));
                $this->displayPoints($simulatedPoints);
                $consolidation = $this->timedCall(
                    'consolidateIdeaPoints',
                    fn () => $researcher->consolidateIdeaPoints($idea, $simulatedPoints)
                );
                $this->displayConsolidationResult($consolidation);
            } else {
                $conflicted = $this->makeSimulatedConflictedPoints();
                $facts = $this->makeSimulatedResolvedFacts();

                $this->info('Using simulated conflicted points and verified facts.');
                $this->newLine();
                $this->comment('Conflicted points input');
                $this->line('Conflict rationale: '.($conflicted->getRationale() ?? '—'));
                $this->displayPoints($conflicted->getPoints());
                $this->newLine();
                $this->comment('Verified facts');
                foreach ($facts as $index => $fact) {
                    if ($fact instanceof Fact) {
                        $this->line('- Fact '.($index + 1).': '.$fact->getFact());
                    }
                }

                $resolvedPoint = $this->timedCall(
                    'resolveIdeaConflictedPointsByFacts',
                    fn () => $researcher->resolveIdeaConflictedPointsByFacts($idea, $conflicted, $facts)
                );
                $this->newLine();
                if ($resolvedPoint === null) {
                    $this->warn('Conflict could not be resolved (returned null).');
                } else {
                    $this->info('Resolved point');
                    $this->displayPoints([$resolvedPoint]);
                }
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
                    'OpenAIResearcherDriver — OpenAI Responses API (uses synthesizer.researcher.drivers.openai config)',
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
     * @param  list<RelevantPoint>  $points
     */
    private function displayPoints(array $points): void
    {
        if ($points === []) {
            $this->warn('No relevant points returned.');

            return;
        }

        $this->table(
            ['#', 'headline', 'relevance', 'evidences'],
            array_map(
                static fn (RelevantPoint $item, int $index): array => [
                    (string) ($index + 1),
                    Str::limit((string) ($item->getHeadline() ?? ''), 72),
                    (string) ($item->getRelevance() ?? '—'),
                    (string) count($item->getEvidences()),
                ],
                $points,
                array_keys($points)
            )
        );

        foreach ($points as $index => $item) {
            $this->newLine();
            $this->comment('Point '.($index + 1).': '.($item->getHeadline() ?? '(no headline)'));
            $this->line(Str::limit((string) ($item->getDescription() ?? ''), 800));
            $this->line('Rationale: '.($item->getRationale() ?? '—'));

            foreach ($item->getEvidences() as $i => $evidence) {
                $this->line('- Evidence '.($i+1).': '.$evidence);
            }
        }
    }

    private function displayConsolidationResult(ConsolidationResult $result): void
    {
        $this->newLine();
        $this->info('Consolidation result');
        $this->line('-----');

        $points = $result->getPoints();
        $conflicts = $result->getConflicts();

        $this->info(sprintf('Resolved points: %d | Conflicts: %d', count($points), count($conflicts)));

        if ($points !== []) {
            $this->newLine();
            $this->comment('Resolved points');
            $this->displayPoints($points);
        }

        if ($conflicts !== []) {
            $this->newLine();
            $this->comment('Conflicts');
            foreach ($conflicts as $index => $conflict) {
                if (! $conflict instanceof ConflictedPoints) {
                    continue;
                }

                $this->newLine();
                $this->line('Conflict '.($index + 1).': '.($conflict->getRationale() ?? 'No rationale'));
                $this->displayPoints($conflict->getPoints());
            }
        }
    }

    /**
     * @return list<RelevantPoint>
     */
    private function makeSimulatedRelevantPoints(): array
    {
        return [
            (new RelevantPoint)
                ->setHeadline('AI copilot adoption is accelerating')
                ->setDescription('Weekly usage grew substantially year-over-year across surveyed teams.')
                ->setEvidences(['68% weekly usage in 2026 vs 41% in Q2 2025'])
                ->setRationale('Indicates strong market pull and mainstream adoption momentum.')
                ->setRelevance(0.92),
            (new RelevantPoint)
                ->setHeadline('Productivity gains vary by team size')
                ->setDescription('Smaller teams report larger gains than enterprise-size teams.')
                ->setEvidences(['<20 engineers: 1.9x gain', '>100 engineers: 1.2x gain'])
                ->setRationale('Shows ROI heterogeneity and implementation constraints.')
                ->setRelevance(0.86),
            (new RelevantPoint)
                ->setHeadline('Reported ROI is consistently above 1.8x')
                ->setDescription('Some reports claim nearly 2x overall productivity improvements.')
                ->setEvidences(['Vendor benchmark estimates near 2x ROI'])
                ->setRationale('Supports a strong-value narrative for broad rollout.')
                ->setRelevance(0.73),
            (new RelevantPoint)
                ->setHeadline('Large enterprises see only modest gains')
                ->setDescription('Independent studies report overall gains closer to 1.1x in large organizations.')
                ->setEvidences(['Independent benchmark: median gain ~1.1x in large enterprises'])
                ->setRationale('Directly challenges optimistic ROI assumptions and suggests context dependence.')
                ->setRelevance(0.74),
            (new RelevantPoint)
                ->setHeadline('Cost controls are becoming mandatory')
                ->setDescription('Finance teams often enforce caps and approval workflows for model usage.')
                ->setEvidences(['Monthly spend increased from $18k to $46k', '57% introduced usage caps'])
                ->setRationale('Cost pressure is a primary blocker despite rising adoption.')
                ->setRelevance(0.84),
            (new RelevantPoint)
                ->setHeadline('Unit economics improve after broad rollout')
                ->setDescription('Several case studies show per-user cost declining after wider internal adoption.')
                ->setEvidences(['Case studies show lower cost per active user after scaling'])
                ->setRationale('Potentially conflicts with pure cost-pressure narratives depending on measurement window.')
                ->setRelevance(0.72),
            (new RelevantPoint)
                ->setHeadline('Governance requirements moved earlier in buying cycle')
                ->setDescription('Security and legal checks are now required before pilot approval in many orgs.')
                ->setEvidences(['72% required data retention and regional-processing terms pre-pilot'])
                ->setRationale('Highlights risk/compliance gatekeeping as part of adoption strategy.')
                ->setRelevance(0.81),
        ];
    }

    private function makeSimulatedConflictedPoints(): ConflictedPoints
    {
        return (new ConflictedPoints)
            ->setRationale('ROI estimates conflict across sources.')
            ->setPoints([
                (new RelevantPoint)
                    ->setHeadline('ROI is around 2x')
                    ->setDescription('Vendor benchmark reports nearly 2x productivity uplift.')
                    ->setEvidences(['Vendor benchmark report'])
                    ->setRationale('Vendor sample highlights best-performing teams.')
                    ->setRelevance(0.76),
                (new RelevantPoint)
                    ->setHeadline('ROI is around 1.2x')
                    ->setDescription('Independent benchmark reports more modest gains.')
                    ->setEvidences(['Independent benchmark'])
                    ->setRationale('Broader sample includes enterprise friction.')
                    ->setRelevance(0.74),
            ]);
    }

    /**
     * @return list<Fact>
     */
    private function makeSimulatedResolvedFacts(): array
    {
        return [
            new Fact('Verified multi-source median ROI is approximately 1.3x over 2 quarters.'),
            new Fact('Performance variance is strongly correlated with team size and process maturity.'),
        ];
    }
}
