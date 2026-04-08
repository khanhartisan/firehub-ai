<?php

namespace App\Console\Commands\LiveTests;

use App\Contracts\SearchEngine\SearchOptions;
use App\Enums\Country;
use App\Enums\Language;
use App\Facades\SearchEngine;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class TestSearchEngineService extends Command
{
    protected $signature = 'live-test:test-search-engine-service
                            {query? : Search query}
                            {--driver= : Driver name (defaults to search_engine.default)}
                            {--limit=10 : Max organic results}
                            {--offset=0 : Result offset}
                            {--language= : hl language tag (e.g. en, fr, zh-CN)}
                            {--country= : gl country code (e.g. US, DE)}';

    protected $description = 'Run a live web search via SearchEngine (calls your configured provider, e.g. SearchAPI.io).';

    public function handle(): int
    {
        $drivers = array_keys(config('search_engine.drivers', []));
        if ($drivers === []) {
            $this->error('No search_engine.drivers configured.');

            return self::FAILURE;
        }

        $defaultDriver = (string) ($this->option('driver') ?: config('search_engine.default', 'google'));
        if (! in_array($defaultDriver, $drivers, true)) {
            $this->error("Unknown driver \"{$defaultDriver}\". Available: ".implode(', ', $drivers));

            return self::FAILURE;
        }

        $driver = $defaultDriver;
        if (! $this->option('driver') && $this->input->isInteractive()) {
            $defaultIndex = array_search(config('search_engine.default', 'google'), $drivers, true);
            $driver = $this->choice('Select driver', $drivers, $defaultIndex === false ? 0 : $defaultIndex);
        }

        $driverConfig = config('search_engine.drivers.'.$driver, []);
        $providerName = is_array($driverConfig) ? ($driverConfig['provider'] ?? 'searchapi') : 'searchapi';
        $providerConfig = config('search_engine.providers.'.$providerName, []);
        $apiKey = is_array($providerConfig) ? ($providerConfig['api_key'] ?? '') : '';
        if (! is_string($apiKey) || trim($apiKey) === '') {
            $this->error(
                "Set the API key for provider \"{$providerName}\" (search_engine.providers.{$providerName}.api_key)."
            );

            return self::FAILURE;
        }

        $query = $this->argument('query');
        if ($query === null || $query === '') {
            $query = $this->ask('Search query', 'laravel');
        }
        $query = trim((string) $query);
        if ($query === '') {
            $this->error('Query cannot be empty.');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));

        $options = SearchOptions::create()
            ->setLimit($limit)
            ->setOffset($offset);

        $lang = $this->resolveLanguage($this->option('language'));
        if ($lang !== null) {
            $options->setLanguage($lang);
        }

        $country = $this->resolveCountry($this->option('country'));
        if ($country !== null) {
            $options->setCountry($country);
        }

        $this->info('SearchEngine::driver('.json_encode($driver).') — query: '.json_encode($query));
        $this->line('limit='.$limit.', offset='.$offset
            .($lang ? ', hl='.$lang->value : '')
            .($country ? ', gl='.$country->value : ''));
        $this->line('-----');

        $start = microtime(true);

        try {
            $results = SearchEngine::driver($driver)->search($query, $options);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $start, 3);
        $this->info('Request time: '.$elapsed.'s');
        $this->line('Total (reported): '.($results->totalEstimated !== null ? number_format($results->totalEstimated) : '—'));
        $this->line('-----');

        $rows = [];
        foreach ($results as $r) {
            $rows[] = [
                (string) ($r->position ?? '—'),
                $this->truncate($r->title, 48),
                $this->truncate($r->url, 56),
                $this->truncate($r->snippet ?? '', 64),
            ];
        }

        $this->table(
            ['#', 'Title', 'URL', 'Snippet'],
            $rows
        );

        $this->line('Results: '.count($results));

        return self::SUCCESS;
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1).'…';
    }

    private function resolveLanguage(?string $raw): ?Language
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $direct = Language::tryFrom($raw);
        if ($direct !== null) {
            return $direct;
        }

        foreach (Language::cases() as $case) {
            if (strcasecmp($case->value, $raw) === 0) {
                return $case;
            }
        }

        $this->warn("Unknown language \"{$raw}\"; hl omitted.");

        return null;
    }

    private function resolveCountry(?string $raw): ?Country
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $code = strtoupper(trim($raw));
        $country = Country::tryFrom($code);
        if ($country !== null) {
            return $country;
        }

        $this->warn("Unknown country \"{$raw}\"; gl omitted.");

        return null;
    }
}
