<?php

namespace Tests\Unit\Contracts\Model\Keyword;

use App\Contracts\Model\Keyword\SearchEngineData;
use App\Contracts\SearchEngine\SearchResult;
use App\Contracts\SearchEngine\SearchResults;
use PHPUnit\Framework\TestCase;

class SearchEngineDataTest extends TestCase
{
    public function test_it_supports_multiple_driver_payloads(): void
    {
        $data = SearchEngineData::fromArray([
            'Google' => [
                'search_results' => [
                    'items' => [
                        SearchResult::fromArray([
                            'title' => 'Example',
                            'url' => 'https://example.com',
                            'position' => 1,
                        ])->toArray(),
                    ],
                    'query' => 'best shoes',
                    'total_estimated' => 1200,
                ],
            ],
            'serper' => [
                'search_results' => [
                    'items' => [],
                    'query' => 'best shoes',
                    'total_estimated' => 980,
                ],
            ],
        ]);

        $this->assertTrue($data->hasDriver('google'));
        $this->assertTrue($data->hasDriver('SERPER'));
        $this->assertInstanceOf(SearchResults::class, $data->getDriverData('google')?->getSearchResults());
        $this->assertSame(1200, $data->getDriverData('google')?->getSearchResults()?->totalEstimated);
        $this->assertSame(980, $data->getDriverData('serper')?->getSearchResults()?->totalEstimated);
    }

    public function test_to_array_and_from_array_are_symmetric(): void
    {
        $source = (new SearchEngineData)
            ->setDriverData('google', [
                'search_results' => [
                    'items' => [],
                    'query' => 'shoes',
                    'total_estimated' => 100,
                ],
            ])
            ->setDriverData('serper', [
                'search_results' => [
                    'items' => [],
                    'query' => 'shoes',
                    'total_estimated' => 90,
                ],
            ]);

        $roundTrip = SearchEngineData::fromArray($source->toArray());

        $this->assertSame($source->toArray(), $roundTrip->toArray());
    }

    public function test_merge_driver_data_keeps_existing_keys(): void
    {
        $data = (new SearchEngineData)
            ->setDriverData('google', [
                'search_results' => [
                    'items' => [],
                    'query' => 'best shoes',
                    'total_estimated' => 100,
                ],
                'meta' => [
                    'source' => 'seed',
                ],
            ])
            ->mergeDriverData('google', [
                'meta' => [
                    'fetched_at' => '2026-04-20T00:00:00+00:00',
                ],
            ]);

        $this->assertSame([
            'source' => 'seed',
            'fetched_at' => '2026-04-20T00:00:00+00:00',
        ], $data->getDriverData('google')?->getMeta());
        $this->assertSame(100, $data->getDriverData('google')?->getSearchResults()?->totalEstimated);
    }
}
