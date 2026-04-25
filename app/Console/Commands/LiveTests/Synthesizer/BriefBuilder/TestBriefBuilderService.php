<?php

namespace App\Console\Commands\LiveTests\Synthesizer\BriefBuilder;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Model\Article\StageData\ResearchStageData;
use App\Contracts\IntentResolver\Intent;
use App\Contracts\Synthesizer\BriefBuilder\BriefBuilder;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\Researcher\RelevantPoint;
use App\Enums\IntentType;
use App\Enums\Language;
use App\Enums\Temporal;
use App\Facades\Synthesizer;
use App\Services\Synthesizer\BriefBuilder\Drivers\BasicBriefBuilderDriver;
use App\Services\Synthesizer\BriefBuilder\Drivers\OpenAIBriefBuilderDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestBriefBuilderService extends Command
{
    protected $signature = 'live-test:synthesizer:test-brief-builder-service';

    protected $description = 'Run a BriefBuilder in isolation (conceive) or load brief builder from a synthesizer driver.';

    public function handle(): int
    {
        $resolution = $this->resolveBriefBuilder();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$briefBuilder, $sourceLabel] = $resolution;

        $ideaTitle = (string) $this->ask('Idea title', 'AI onboarding playbook for B2B SaaS teams');
        $ideaDescription = (string) $this->ask(
            'Idea description',
            'How product and growth teams can roll out AI onboarding flows with measurable activation gains.'
        );
        $ideaReason = (string) $this->ask(
            'Idea reason',
            'High strategic fit with conversion and activation priorities.'
        );

        $context = $this->buildSemanticContext();
        $idea = $this->makeIdea($ideaTitle, $ideaDescription, $ideaReason);

        $this->newLine();
        $this->info('BriefBuilder: '.Str::afterLast($briefBuilder::class, '\\').' | '.$sourceLabel);
        $this->line('-----');

        try {
            $brief = $this->timedCall('conceive', fn () => $briefBuilder->conceive($idea, $context));
            $this->displayBrief($brief);

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
     * @return array{0: BriefBuilder, 1: string}|null
     */
    private function resolveBriefBuilder(): ?array
    {
        $mode = $this->choice(
            'How should the brief builder be loaded?',
            [
                'Direct: construct brief builder only (no Synthesizer)',
                'From synthesizer driver: BriefBuilder (full wiring)',
            ],
            0
        );

        if (str_starts_with($mode, 'Direct')) {
            $impl = $this->choice(
                'Which implementation?',
                [
                    'BasicBriefBuilderDriver — deterministic/local',
                    'OpenAIBriefBuilderDriver — OpenAI Responses API (uses synthesizer.openai_brief_builder config)',
                ],
                1
            );

            if (str_contains($impl, 'Basic')) {
                return [new BasicBriefBuilderDriver, 'direct · BasicBriefBuilderDriver'];
            }

            return [
                $this->laravel->make(OpenAIBriefBuilderDriver::class),
                'direct · OpenAIBriefBuilderDriver (container)',
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

        $briefBuilder = Synthesizer::driver($driverName)->getBriefBuilder();

        return [$briefBuilder, "synthesizer driver «{$driverName}» · BriefBuilder"];
    }

    private function makeIdea(string $title, string $description, string $reason): Idea
    {
        $intent = (new Intent)
            ->setTitle($title)
            ->setDescription($description)
            ->setLanguage(Language::EN)
            ->setTemporal(Temporal::TOPICAL)
            ->setTypes([IntentType::INFORMATIONAL]);

        return new Idea($intent, 0.88, $reason);
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
        ];
    }

    private function makeResearchStageDataFixture(): ResearchStageData
    {
        // Mirror HandleBriefStage: context receives getResearchStageData()->getPoints()
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

    private function displayBrief(\App\Contracts\Synthesizer\BriefBuilder\Brief $brief): void
    {
        $this->info('Brief result');
        $this->table(
            ['Field', 'Value'],
            [
                ['temporal', $brief->getTemporal()?->value ?? '—'],
                ['title', Str::limit((string) ($brief->getTitle() ?? ''), 120)],
                ['description', Str::limit((string) ($brief->getDescription() ?? ''), 180)],
                ['goal', $brief->getGoal()?->value ?? '—'],
                ['voice', $brief->getVoice()?->value ?? '—'],
                ['tone', $brief->getTone()?->value ?? '—'],
                ['instructions_count', (string) count($brief->getInstructions())],
                ['audiences_count', (string) count($brief->getAudiences())],
                ['reference_page_ids_count', (string) count($brief->getReferencePageIds())],
            ]
        );

        $instructions = $brief->getInstructions();
        if ($instructions !== []) {
            $this->newLine();
            $this->comment('Instructions');
            foreach ($instructions as $index => $line) {
                $this->line(sprintf('  %d. %s', $index + 1, $line));
            }
        }

        $audiences = $brief->getAudiences();
        if ($audiences !== []) {
            $this->newLine();
            $this->comment('Audiences');
            $rows = [];
            foreach ($audiences as $index => $audience) {
                $rows[] = [
                    (string) ($index + 1),
                    (string) ($audience->getName() ?? '—'),
                    (string) ($audience->getKnowledgeLevel()?->value ?? '—'),
                    (string) ($audience->getLanguage()?->value ?? '—'),
                    (string) count($audience->getCountries()),
                ];
            }
            $this->table(['#', 'name', 'knowledge_level', 'language', 'countries'], $rows);
        }

        $referencePageIds = $brief->getReferencePageIds();
        if ($referencePageIds !== []) {
            $this->newLine();
            $this->comment('Reference page IDs');
            foreach ($referencePageIds as $id) {
                $this->line('- '.$id);
            }
        }
    }
}
