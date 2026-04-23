<?php

namespace App\Console\Commands\LiveTests;

use App\Contracts\CommonData\Conflict;
use App\Contracts\CommonData\Fact;
use App\Contracts\CommonData\Point;
use App\Contracts\CommonData\SemanticContext;
use App\Facades\FactChecker;
use Illuminate\Console\Command;
use Throwable;

class TestFactCheckerService extends Command
{
    protected $signature = 'live-test:test-fact-checker-service
                            {--driver= : Driver name (defaults to factchecker.default)}
                            {--operation= : Operation to test: verify|resolveConflict}
                            {--headline= : Point headline}
                            {--description= : Point description}
                            {--evidence=* : Evidence lines (repeat option for multiple)}
                            {--conflict-fact=* : Conflict fact lines (repeat option for multiple)}
                            {--context=* : Context key=value entries (repeat option for multiple)}';

    protected $description = 'Run FactChecker live test for verify() or resolveConflict().';

    public function handle(): int
    {
        $drivers = array_keys(config('factchecker.drivers', []));
        if ($drivers === []) {
            $this->error('No factchecker.drivers configured.');

            return self::FAILURE;
        }

        $driver = (string) ($this->option('driver') ?: config('factchecker.default', 'basic'));
        if (! in_array($driver, $drivers, true)) {
            $this->error("Unknown driver \"{$driver}\". Available: ".implode(', ', $drivers));

            return self::FAILURE;
        }

        if (! $this->option('driver') && $this->input->isInteractive()) {
            $defaultIndex = array_search(config('factchecker.default', 'basic'), $drivers, true);
            $driver = $this->choice('Select driver', $drivers, $defaultIndex === false ? 0 : $defaultIndex);
        }

        $operations = ['verify', 'resolveConflict'];
        $operation = (string) ($this->option('operation') ?? '');
        if ($operation !== '' && ! in_array($operation, $operations, true)) {
            $this->error('Unknown operation "'.$operation.'". Available: '.implode(', ', $operations));

            return self::FAILURE;
        }
        if ($operation === '') {
            if ($this->input->isInteractive()) {
                $operation = $this->choice('Select operation to test', $operations, 0);
            } else {
                $operation = 'verify';
            }
        }

        $point = $this->buildPointFromInput();
        $conflict = $this->buildConflictFromInput($point);
        $context = $this->buildContextFromInput();
        $factChecker = FactChecker::driver($driver);

        $this->info("Calling FactChecker::{$operation}() / Driver: ".$driver);
        $this->line('-----');

        try {
            if ($operation === 'verify') {
                $verifyStart = microtime(true);
                $verification = $factChecker->verify($point, $context);
                $verifyElapsed = round(microtime(true) - $verifyStart, 3);
            } else {
                $resolveStart = microtime(true);
                $resolvedFacts = $factChecker->resolveConflict($conflict, $context);
                $resolveElapsed = round(microtime(true) - $resolveStart, 3);
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }

        if ($operation === 'verify') {
            $this->info("verify() processing time: {$verifyElapsed} seconds");
            $this->line('-----');

            $this->table(
                ['Field', 'Value'],
                [
                    ['headline', $point->getHeadline() ?? '—'],
                    ['description', $point->getDescription() ?? '—'],
                    ['evidences_count', (string) count($point->getEvidences())],
                    ['is_valid', $verification->getIsValid() === null ? 'null' : ($verification->getIsValid() ? 'true' : 'false')],
                    ['confidence', $verification->getConfidence() !== null ? (string) $verification->getConfidence() : 'null'],
                    ['reasoning', $verification->getReasoning() ?? '—'],
                ]
            );
        } else {
            $this->info("resolveConflict() processing time: {$resolveElapsed} seconds");
            $this->line('-----');
            $this->table(
                ['Resolved fact', 'is_valid', 'confidence', 'reasoning'],
                array_map(static function (Fact $fact): array {
                    $verification = $fact->getVerification();

                    return [
                        $fact->getFact(),
                        $verification?->getIsValid() === null ? 'null' : ($verification->getIsValid() ? 'true' : 'false'),
                        $verification?->getConfidence() !== null ? (string) $verification->getConfidence() : 'null',
                        $verification?->getReasoning() ?? '—',
                    ];
                }, $resolvedFacts)
            );
        }

        return self::SUCCESS;
    }

    protected function buildPointFromInput(): Point
    {
        $headline = trim((string) ($this->option('headline') ?? ''));
        $description = trim((string) ($this->option('description') ?? ''));
        $evidences = array_values(array_filter(
            (array) $this->option('evidence'),
            static fn ($evidence): bool => is_string($evidence) && trim($evidence) !== ''
        ));

        if ($headline === '') {
            $headline = 'Remote work can improve employee productivity.';
        }

        if ($description === '') {
            $description = 'A hybrid setup is claimed to increase output when teams use clear async workflows.';
        }

        if ($evidences === []) {
            $evidences = [
                'A 2024 internal pilot reported a 12% increase in completed sprint tickets.',
                'Team leads noted fewer meeting hours and more focused work blocks.',
            ];
        }

        return (new Point)
            ->setHeadline($headline)
            ->setDescription($description)
            ->setEvidences($evidences);
    }

    protected function buildConflictFromInput(Point $point): Conflict
    {
        $conflictFacts = array_values(array_filter(
            (array) $this->option('conflict-fact'),
            static fn ($fact): bool => is_string($fact) && trim($fact) !== ''
        ));

        if ($conflictFacts === []) {
            $conflictFacts = [
                'Remote work increased team productivity by 12% in 2024.',
                'Remote work increased team productivity by 18% in 2024.',
            ];
        }

        return (new Conflict)
            ->setFacts(array_map(
                static fn (string $fact): Fact => new Fact($fact),
                $conflictFacts
            ))
            ->setRationale('Same claim with conflicting percentage figures; resolve into one corrected percentage.');
    }

    protected function buildContextFromInput(): ?SemanticContext
    {
        $rawContext = (array) $this->option('context');
        if ($rawContext === []) {
            return null;
        }

        $context = new SemanticContext();
        foreach ($rawContext as $entry) {
            if (! is_string($entry) || ! str_contains($entry, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $entry, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            $context->set($key, "Live-test context value for {$key}", $value);
        }

        return $context->toArray() === [] ? null : $context;
    }
}
