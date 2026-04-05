<?php

namespace App\Console\Commands\LiveTests;

use App\Contracts\VerticalResolver\Vertical;
use App\Facades\VerticalResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

class TestVerticalResolverService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live-test:test-vertical-resolver-service';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run VerticalResolver against sample HTML and fixture verticals (interactive driver).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $verticalsResourcePath = resource_path('sample-verticals/sample-verticals-for-vertical-resolver.json');

        if (! is_file($verticalsResourcePath)) {
            $this->error("Missing verticals fixture: {$verticalsResourcePath}");

            return self::FAILURE;
        }

        try {
            /** @var array{verticals?: array<int, array<string, mixed>>} $payload */
            $payload = json_decode(file_get_contents($verticalsResourcePath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Invalid verticals JSON: '.$e->getMessage());

            return self::FAILURE;
        }

        $verticalData = $payload['verticals'] ?? [];

        if ($verticalData === []) {
            $this->error('Verticals fixture must define a non-empty "verticals" array.');

            return self::FAILURE;
        }

        /** @var Vertical[] $verticals */
        $verticals = array_map(
            fn (array $data) => Vertical::fromArray($data),
            $verticalData
        );

        $samplePath = 'live-tests/sample-page-for-vertical-resolver-service.html';
        $htmlResourcePath = resource_path('sample-html/sample-page-for-page-classifier.html');

        if (! Storage::exists($samplePath)) {
            if (! is_file($htmlResourcePath)) {
                $this->error("Missing sample HTML fixture: {$htmlResourcePath}");

                return self::FAILURE;
            }

            Storage::put($samplePath, file_get_contents($htmlResourcePath));
        }

        $html = Storage::get($samplePath);

        $drivers = array_keys(config('verticalresolver.drivers'));
        $defaultIndex = array_search(config('verticalresolver.default'), $drivers, true);
        $driver = $this->choice(
            'Select driver',
            $drivers,
            $defaultIndex === false ? 0 : $defaultIndex
        );

        $action = $this->choice(
            'Select action',
            ['resolve', 'propose'],
            0
        );

        $start = microtime(true);

        if ($action === 'resolve') {
            $this->info('Calling VerticalResolver::resolve() / Driver: '.$driver);
            $matches = VerticalResolver::driver($driver)->resolve($html, $verticals);
            $this->info('Processing time: '.(microtime(true) - $start).' seconds');
            $this->line('-----');

            $rows = [];
            foreach ($matches as $match) {
                $rows[] = [
                    $match->getVerticalIdentifier(),
                    (string) $match->getConfidence(),
                ];
            }

            if ($rows === []) {
                $this->warn('No matches returned.');
            } else {
                $this->table(['vertical_identifier', 'confidence'], $rows);
            }

            return self::SUCCESS;
        }

        $this->info('Calling VerticalResolver::propose() / Driver: '.$driver);
        if ($driver === 'keyword') {
            $this->comment('Note: the keyword driver returns no proposals by design.');
        }

        $proposals = VerticalResolver::driver($driver)->propose($html, $verticals);
        $this->info('Processing time: '.(microtime(true) - $start).' seconds');
        $this->line('-----');

        $rows = [];
        foreach ($proposals as $vertical) {
            $rows[] = [
                $vertical->getIdentifier() ?? $vertical->getName(),
                $vertical->getName(),
                Str::limit((string) ($vertical->getDescription() ?? ''), 120),
            ];
        }

        if ($rows === []) {
            $this->warn('No proposals returned.');
        } else {
            $this->table(['identifier', 'name', 'description'], $rows);
        }

        return self::SUCCESS;
    }
}
