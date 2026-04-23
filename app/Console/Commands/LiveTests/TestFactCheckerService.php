<?php

namespace App\Console\Commands\LiveTests;

use App\Contracts\CommonData\Point;
use App\Contracts\CommonData\SemanticContext;
use App\Facades\FactChecker;
use Illuminate\Console\Command;
use Throwable;

class TestFactCheckerService extends Command
{
    protected $signature = 'live-test:test-fact-checker-service
                            {--driver= : Driver name (defaults to factchecker.default)}
                            {--headline= : Point headline}
                            {--description= : Point description}
                            {--evidence=* : Evidence lines (repeat option for multiple)}
                            {--context=* : Context key=value entries (repeat option for multiple)}';

    protected $description = 'Run FactChecker against a sample point (interactive driver and optional custom payload).';

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

        $point = $this->buildPointFromInput();
        $context = $this->buildContextFromInput();

        $this->info('Calling FactChecker::verifyPoint() / Driver: '.$driver);
        $this->line('-----');

        $start = microtime(true);

        try {
            $verification = FactChecker::driver($driver)->verifyPoint($point, $context);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }

        $this->info('Processing time: '.round(microtime(true) - $start, 3).' seconds');
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
