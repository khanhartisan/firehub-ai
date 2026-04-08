<?php

namespace Tests\Unit\Services\SearchEngine\Drivers;

use App\Contracts\SearchEngine\SearchOptions;
use App\Enums\Country;
use App\Enums\Language;
use App\Services\SearchEngine\Drivers\SearchapiGoogleDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SearchapiGoogleDriverTest extends TestCase
{
    /**
     * @return array{0: Client, 1: MockHandler}
     */
    protected function clientWithMockQueue(MockHandler $mock): array
    {
        $handler = HandlerStack::create($mock);

        return [
            new Client([
                'handler' => $handler,
                'base_uri' => 'https://www.searchapi.io/',
            ]),
            $mock,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function successPayload(array $organic, int $totalResults = 1_000_000): array
    {
        return [
            'search_metadata' => [
                'status' => 'Success',
            ],
            'search_information' => [
                'total_results' => $totalResults,
            ],
            'organic_results' => $organic,
        ];
    }

    public function test_it_throws_when_api_key_missing(): void
    {
        [$client] = $this->clientWithMockQueue(new MockHandler([]));
        $driver = new SearchapiGoogleDriver([
            'api_key' => '',
            'base_url' => 'https://www.searchapi.io',
        ], $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEARCHAPI_API_KEY');

        $driver->search('test');
    }

    public function test_empty_query_returns_empty_results(): void
    {
        [$client] = $this->clientWithMockQueue(new MockHandler([]));
        $driver = new SearchapiGoogleDriver([
            'api_key' => 'secret',
            'base_url' => 'https://www.searchapi.io',
        ], $client);

        $results = $driver->search('   ');

        $this->assertCount(0, $results);
        $this->assertSame('', $results->query);
        $this->assertNull($results->totalEstimated);
    }

    public function test_it_maps_organic_results_to_search_results(): void
    {
        $payload = $this->successPayload([
            [
                'position' => 1,
                'title' => 'Example title',
                'link' => 'https://example.com/page',
                'snippet' => 'A snippet.',
            ],
        ], 42);

        [$client] = $this->clientWithMockQueue(new MockHandler([
            new Response(200, [], json_encode($payload)),
        ]));

        $driver = new SearchapiGoogleDriver([
            'api_key' => 'secret',
            'base_url' => 'https://www.searchapi.io',
        ], $client);

        $results = $driver->search('hello world');

        $this->assertSame('hello world', $results->query);
        $this->assertSame(42, $results->totalEstimated);
        $this->assertCount(1, $results);

        $first = $results->items[0];
        $this->assertSame('Example title', $first->title);
        $this->assertSame('https://example.com/page', $first->url);
        $this->assertSame('A snippet.', $first->snippet);
        $this->assertSame(1, $first->position);
    }

    public function test_it_sends_hl_and_gl_when_options_set(): void
    {
        $payload = $this->successPayload([
            [
                'title' => 'T',
                'link' => 'https://x.test',
            ],
        ]);

        $mock = new MockHandler([
            new Response(200, [], json_encode($payload)),
        ]);

        $container = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($container));

        $client = new Client([
            'handler' => $stack,
            'base_uri' => 'https://www.searchapi.io/',
        ]);

        $driver = new SearchapiGoogleDriver([
            'api_key' => 'secret',
            'base_url' => 'https://www.searchapi.io',
        ], $client);

        $driver->search('q', SearchOptions::create()
            ->setLanguage(Language::FR)
            ->setCountry(Country::DE));

        $this->assertNotEmpty($container);
        $uri = $container[0]['request']->getUri();
        $query = [];
        parse_str($uri->getQuery(), $query);

        $this->assertSame('fr', $query['hl']);
        $this->assertSame('de', $query['gl']);
        $this->assertSame('google', $query['engine']);
        $this->assertSame('secret', $query['api_key']);
    }

    public function test_non_200_response_throws(): void
    {
        [$client] = $this->clientWithMockQueue(new MockHandler([
            new Response(500, [], 'Server error'),
        ]));

        $driver = new SearchapiGoogleDriver([
            'api_key' => 'secret',
            'base_url' => 'https://www.searchapi.io',
        ], $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');

        $driver->search('query');
    }

    public function test_search_metadata_not_success_throws(): void
    {
        [$client] = $this->clientWithMockQueue(new MockHandler([
            new Response(200, [], json_encode([
                'search_metadata' => ['status' => 'Error'],
            ])),
        ]));

        $driver = new SearchapiGoogleDriver([
            'api_key' => 'secret',
            'base_url' => 'https://www.searchapi.io',
        ], $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not succeed');

        $driver->search('query');
    }

    public function test_invalid_json_body_throws(): void
    {
        [$client] = $this->clientWithMockQueue(new MockHandler([
            new Response(200, [], 'not-json{{{'),
        ]));

        $driver = new SearchapiGoogleDriver([
            'api_key' => 'secret',
            'base_url' => 'https://www.searchapi.io',
        ], $client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid JSON');

        $driver->search('query');
    }

    public static function rowsMissingLinkProvider(): array
    {
        return [
            'no link key' => [['title' => 'Only title']],
            'empty link' => [['title' => 'T', 'link' => '']],
        ];
    }

    #[DataProvider('rowsMissingLinkProvider')]
    public function test_it_skips_rows_without_link(array $row): void
    {
        $payload = $this->successPayload([
            $row,
            [
                'title' => 'Valid',
                'link' => 'https://ok.test',
            ],
        ]);

        [$client] = $this->clientWithMockQueue(new MockHandler([
            new Response(200, [], json_encode($payload)),
        ]));

        $driver = new SearchapiGoogleDriver([
            'api_key' => 'secret',
            'base_url' => 'https://www.searchapi.io',
        ], $client);

        $results = $driver->search('q');

        $this->assertCount(1, $results);
        $this->assertSame('https://ok.test', $results->items[0]->url);
    }
}
