<?php

namespace App\Jobs;

use App\Contracts\Model\Keyword\SearchEngineData;
use App\Contracts\SearchEngine\SearchOptions;
use App\Enums\KeywordStatus;
use App\Enums\Queue;
use App\Facades\SearchEngine;
use App\Jobs\Concerns\HasManualLock;
use App\Models\Keyword;
use App\Models\KeywordPage;
use App\Models\Page;
use App\Utils\UrlNormalizer;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class KeywordResearchJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;
    use HasManualLock;

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public int $validityDuration = 86400;

    protected Keyword $keyword;

    protected int $maxResearchAttempts = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(Keyword $keyword,
                                protected array $searchEngineDrivers = [],
                                protected int $limitPerDriver = 5,
                                protected bool $forceResearch = false)
    {
        $this->keyword = $keyword->withoutRelations();

        $this->onQueue(Queue::KEYWORD_RESEARCHING->value);
    }

    public function uniqueId(): string
    {
        return $this->keyword->id;
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(): void
    {
        if (!$lock = $this->getManualLock() or !$lock->get()) {
            return;
        }

        $keyword = $this->keyword;

        // Mark as researched if the last research remains valid
        if (!$this->forceResearch
            and $keyword->researched_at
            and abs(now()->diffInSeconds($keyword->researched_at)) <= $this->validityDuration
        ) {
            $keyword->status = KeywordStatus::RESEARCHED;
            $keyword->save();
            return;
        }

        // Mark as researching
        $keyword->researched_at = null;
        $keyword->status = KeywordStatus::RESEARCHING;
        $keyword->touch();

        try {

            // Perform search
            foreach ($this->getSearchEngineDrivers() as $driver) {
                $handleResult = $this->performSearch($driver);

                // Just processed, re-dispatch signal
                if (is_null($handleResult)) {
                    $this->reDispatch();
                    return;
                }

                // Failed
                if (!$handleResult) {
                    throw new Exception('Failed to perform search for keyword: '.$keyword->keyword.' (Language: '.($keyword->language?->value ?? 'null').' / Country: '.($keyword->country?->value ?? 'null').')');
                }
            }

            // Ensure related pages exist
            $this->createRelatedPages();

            // Now wait for all the pages to be scraped
            /** @var Page $page */
            foreach ($keyword->pages()->get() as $page) {

                // If the page isn't scraped, re-dispatch
                if (!$page->scraping_status->isFinal()) {
                    // We delay for 60 seconds because page scraping takes time
                    $this->reDispatch(60);
                    return;
                }
            }

            // All good, mark the keyword as researched
            $keyword->attempts = 0;
            $keyword->researched_at = now();
            $keyword->status = KeywordStatus::RESEARCHED;
            $keyword->save();

        } catch (Exception $e) {

            // Increase attempts count
            $keyword->attempts = intval($keyword->attempts) + 1;
            $keyword->researched_at = null;

            // Set keyword status
            if ($keyword->attempts >= $this->maxResearchAttempts) {
                $keyword->status = KeywordStatus::ERROR;
            } else {
                $keyword->status = KeywordStatus::RESEARCHING;
                $this->reDispatch(60);
            }

            // Save error logs
            $errorLogs = $keyword->error_logs ?? '';
            $errorLogs .= "\n".$e->getMessage()."\n".$e->getTraceAsString();
            if (strlen($errorLogs) > 10000) {
                $errorLogs = '...trimmed...'."\n".substr($errorLogs, -10000);
            }
            $keyword->error_logs = $errorLogs;

            // Save keyword
            $keyword->save();

        } finally {
            $lock->release();
        }
    }

    /**
     * Perform search using a SearchEngine driver
     *
     * @param string $driver
     * @return bool|null True if handled, false if failed, null if just processed and needs re-dispatch
     */
    protected function performSearch(string $driver): ?bool
    {
        $searchEngineData = $this->getKeywordSearchEngineData();

        // Return true if we have recent search results
        if ($driverData = $searchEngineData->getDriverData($driver, true)
            and $searchResults = $driverData->getSearchResults()
            and $searchResults->getUpdatedAt()
            and abs($searchResults->getUpdatedAt()->diffInSeconds(now())) <= $this->validityDuration
        ) {
            return true;
        }

        // Perform search
        $keyword = $this->keyword;
        $searchEngine = SearchEngine::driver($driver);
        $searchResults = $searchEngine->search(
            $keyword->keyword,
            new SearchOptions()
                ->setLanguage($keyword->language)
                ->setCountry($keyword->country)
                ->setLimit($this->limitPerDriver)
        );

        // Save search results
        $driverData->setSearchResults($searchResults);

        // Save keyword
        $keyword->touchQuietly();

        // Return null for re-dispatch signal
        // because we only want to perform one api call per job execution
        return null;
    }

    protected function createRelatedPages(): void
    {
        $keyword = $this->keyword;

        foreach ($this->getSearchEngineDrivers() as $driver) {
            $searchResults = $this->getKeywordSearchEngineData()
                ->getDriverData($driver)
                ?->getSearchResults();

            if (!$searchResults) {
                continue;
            }

            foreach ($searchResults->items as $index => $searchResult) {
                $normalizedUrl = UrlNormalizer::normalize($searchResult->url);
                if ($normalizedUrl === '' || !str_starts_with($normalizedUrl, 'http')) {
                    continue;
                }

                $position = $searchResult->position ?? ($index + 1);
                if ($position <= 0) {
                    $position = $index + 1;
                }

                $page = Page::query()->where('url_hash', Page::makeUrlHash($normalizedUrl))->first();
                if (! $page) {
                    $page = Page::query()->create([
                        'url' => $normalizedUrl,
                        'ignore_scraping_budget' => true,
                    ]);
                } elseif (! $page->ignore_scraping_budget
                    and !$page->scraping_status?->isFinal()
                ) {
                    $page->ignore_scraping_budget = true;
                    $page->save();
                }

                KeywordPage::query()->updateOrCreate(
                    [
                        'search_engine_driver' => $driver,
                        'keyword_id' => $keyword->id,
                        'page_id' => $page->id,
                    ],
                    [
                        'position' => $position,
                    ]
                );
            }
        }
    }

    protected function getKeywordSearchEngineData(): SearchEngineData
    {
        return $this->keyword->search_engine_data ??= new SearchEngineData();
    }

    protected function getSearchEngineDrivers(): array
    {
        return array_filter(
            $this->searchEngineDrivers,
            function ($driver) {
                return !!config('search_engine.drivers.' . $driver);
            }
        ) ?: [config('search_engine.default')];
    }

    /**
     * @throws Exception
     */
    protected function reDispatch(?int $delay = null): void
    {
        $this->getManualLock()->release();

        static::dispatch(
            $this->keyword,
            $this->getSearchEngineDrivers(),
            $this->limitPerDriver,
            $this->forceResearch
        )->delay($delay);
    }
}
