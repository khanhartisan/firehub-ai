<?php

namespace App\Console\Commands\LiveTests\Synthesizer\IdeaForge;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\Synthesizer\IdeaForge\Idea;
use App\Contracts\Synthesizer\IdeaForge\IdeaAdvisor;
use App\Contracts\Synthesizer\IdeaForge\IntentTypeSuggestion;
use App\Facades\Synthesizer;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\BasicIdeaAdvisorDriver;
use App\Services\Synthesizer\IdeaForge\IdeaAdvisor\Drivers\OpenAIIdeaAdvisorDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestIdeaAdvisorService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live-test:synthesizer:test-idea-advisor-service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run an IdeaAdvisor in isolation (suggestTemporal, suggestIntentTypes, brainstorm) or optionally load advisors from a synthesizer driver’s IdeaForge.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $resolution = $this->resolveIdeaAdvisor();
        if ($resolution === null) {
            return self::FAILURE;
        }

        [$advisor, $sourceLabel] = $resolution;

        $clientId = (string) $this->ask('Client id', 'live-test-client');
        $defaultContext = <<<'CTX'
A weekly B2B newsletter for SaaS operators: product launches, pricing moves, hiring signals, and one tactical takeaway. Audience prefers concise analysis and links to primary sources.
CTX;
        $context = (new SemanticContext)->set(
            'article_context',
            'Editorial / business context',
            (string) $this->ask('Editorial / business context', $defaultContext)
        );

        $action = $this->choice(
            'What to run',
            [
                'suggest_temporal',
                'suggest_intent_types',
                'brainstorm',
                'full_pipeline',
            ],
            3
        );

        $limit = 5;
        if ($action === 'brainstorm' || $action === 'full_pipeline') {
            $limit = max(1, min(20, (int) $this->ask('Brainstorm limit (ideas)', '5')));
        }

        $this->newLine();
        $this->info("Advisor: {$advisor->getIdentifier()} | {$sourceLabel}");
        $this->line('-----');

        try {
            if ($action === 'suggest_temporal') {
                return $this->runSuggestTemporal($advisor, $clientId, $context);
            }

            if ($action === 'suggest_intent_types') {
                return $this->runSuggestIntentTypes($advisor, $clientId, $context);
            }

            if ($action === 'brainstorm') {
                $temporal = $this->timedCall('suggestTemporal', fn () => $advisor->suggestTemporal($clientId, $context));
                $intentTypes = $this->timedCall('suggestIntentTypes', fn () => $advisor->suggestIntentTypes($clientId, $context));

                $this->displayTemporalSuggestions($temporal);
                $this->displayIntentTypeSuggestions($intentTypes);

                $ideas = $this->timedCall('brainstorm', fn () => $advisor->brainstorm($temporal, $intentTypes, $context, $limit));
                $this->displayIdeas($ideas);

                return self::SUCCESS;
            }

            // full_pipeline
            $temporal = $this->timedCall('suggestTemporal', fn () => $advisor->suggestTemporal($clientId, $context));
            $this->displayTemporalSuggestions($temporal);

            $intentTypes = $this->timedCall('suggestIntentTypes', fn () => $advisor->suggestIntentTypes($clientId, $context));
            $this->displayIntentTypeSuggestions($intentTypes);

            $ideas = $this->timedCall('brainstorm', fn () => $advisor->brainstorm($temporal, $intentTypes, $context, $limit));
            $this->displayIdeas($ideas);

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
     * @return array{0: IdeaAdvisor, 1: string}|null
     */
    private function resolveIdeaAdvisor(): ?array
    {
        $mode = $this->choice(
            'How should the idea advisor be loaded?',
            [
                'Direct: construct advisor only (no Synthesizer)',
                'From synthesizer driver: IdeaForge advisors (full wiring)',
            ],
            0
        );

        if (str_starts_with($mode, 'Direct')) {
            $impl = $this->choice(
                'Which implementation?',
                [
                    'BasicIdeaAdvisorDriver — local / deterministic',
                    'OpenAIIdeaAdvisorDriver — OpenAI Responses API (uses synthesizer.idea_advisor.drivers.openai config)',
                ],
                0
            );

            if (str_contains($impl, 'Basic')) {
                $advisor = new BasicIdeaAdvisorDriver;

                return [$advisor, 'direct · BasicIdeaAdvisorDriver'];
            }

            $advisor = $this->laravel->make(OpenAIIdeaAdvisorDriver::class);

            return [$advisor, 'direct · OpenAIIdeaAdvisorDriver (container)'];
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

        $synthesizer = Synthesizer::driver($driverName);
        $advisors = $synthesizer->getIdeaForge()->getIdeaAdvisors();

        if ($advisors === []) {
            $this->error('IdeaForge has no idea advisors for this driver.');

            return null;
        }

        $advisorLabels = [];
        foreach ($advisors as $advisor) {
            $advisorLabels[] = sprintf(
                '%s — %s (weight %s)',
                $advisor->getIdentifier(),
                Str::limit($advisor->getDescription() ?? '', 60),
                (string) $advisor->getWeight()
            );
        }

        $pickedLabel = $this->choice('Select idea advisor', $advisorLabels, 0);
        $advisorIndex = array_search($pickedLabel, $advisorLabels, true);
        if ($advisorIndex === false) {
            $this->error('Could not resolve selected advisor.');

            return null;
        }

        return [
            $advisors[$advisorIndex],
            "synthesizer driver «{$driverName}» · IdeaForge",
        ];
    }

    private function runSuggestTemporal(IdeaAdvisor $advisor, string $clientId, SemanticContext $context): int
    {
        $temporal = $this->timedCall('suggestTemporal', fn () => $advisor->suggestTemporal($clientId, $context));
        $this->displayTemporalSuggestions($temporal);

        return self::SUCCESS;
    }

    private function runSuggestIntentTypes(IdeaAdvisor $advisor, string $clientId, SemanticContext $context): int
    {
        $intentTypes = $this->timedCall('suggestIntentTypes', fn () => $advisor->suggestIntentTypes($clientId, $context));
        $this->displayIntentTypeSuggestions($intentTypes);

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

    /**
     * @param  list<TemporalSuggestion>  $suggestions
     */
    private function displayTemporalSuggestions(array $suggestions): void
    {
        if ($suggestions === []) {
            $this->warn('No temporal suggestions returned.');

            return;
        }

        $rows = [];
        foreach ($suggestions as $i => $s) {
            $rows[] = [
                (string) ($i + 1),
                $s->getTemporal()->value,
                $s->getConfidence() !== null ? (string) $s->getConfidence() : '—',
                Str::limit((string) ($s->getReason() ?? ''), 80),
            ];
        }
        $this->info('Temporal suggestions');
        $this->table(['#', 'temporal', 'confidence', 'reason'], $rows);
        $this->line('-----');
    }

    /**
     * @param  list<IntentTypeSuggestion>  $suggestions
     */
    private function displayIntentTypeSuggestions(array $suggestions): void
    {
        if ($suggestions === []) {
            $this->warn('No intent type suggestions returned.');

            return;
        }

        $rows = [];
        foreach ($suggestions as $i => $s) {
            $it = $s->getIntentType();
            $rows[] = [
                (string) ($i + 1),
                $it->name.' ('.$it->value.')',
                $s->getConfidence() !== null ? (string) $s->getConfidence() : '—',
                Str::limit((string) ($s->getReason() ?? ''), 80),
            ];
        }
        $this->info('Intent type suggestions');
        $this->table(['#', 'intent_type', 'confidence', 'reason'], $rows);
        $this->line('-----');
    }

    /**
     * @param  list<Idea>  $ideas
     */
    private function displayIdeas(array $ideas): void
    {
        if ($ideas === []) {
            $this->warn('No ideas returned.');

            return;
        }

        $rows = [];
        foreach ($ideas as $i => $idea) {
            $intent = $idea->getIntent();
            $types = $intent->getTypes();
            $typeLabel = $types !== [] ? $types[0]->name.' ('.$types[0]->value.')' : '—';

            $rows[] = [
                (string) ($i + 1),
                Str::limit($intent->getTitle() ?? '', 48),
                $intent->getTemporal()->value,
                $typeLabel,
                $idea->getConfidence() !== null ? (string) $idea->getConfidence() : '—',
                Str::limit((string) ($idea->getReason() ?? ''), 56),
            ];
        }

        $this->info('Ideas');
        $this->table(['#', 'title', 'temporal', 'intent_type', 'confidence', 'reason'], $rows);

        foreach ($ideas as $i => $idea) {
            $intent = $idea->getIntent();
            $this->newLine();
            $this->comment('Idea '.($i + 1).': '.$intent->getTitle());
            $this->line(Str::limit($intent->getDescription() ?? '', 1200));
        }
    }
}
