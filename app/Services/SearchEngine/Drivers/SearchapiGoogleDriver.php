<?php

namespace App\Services\SearchEngine\Drivers;

use App\Contracts\SearchEngine\SearchEngine;
use App\Contracts\SearchEngine\SearchOptions;
use App\Contracts\SearchEngine\SearchResult;
use App\Contracts\SearchEngine\SearchResults;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/**
 * Google web search via SearchAPI.io ({@link https://www.searchapi.io/docs/google}).
 *
 * Receives merged provider settings plus optional `google` driver overrides from the manager.
 */
class SearchapiGoogleDriver implements SearchEngine
{
    private const int RESULTS_PER_PAGE = 10;

    protected Client $client;

    protected string $apiKey;

    public function __construct(
        protected array $config = [],
        ?Client $httpClient = null,
    ) {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'https://www.searchapi.io'), '/').'/';
        $this->apiKey = (string) ($this->config['api_key'] ?? '');
        $this->client = $httpClient ?? new Client([
            'base_uri' => $baseUrl,
            'timeout' => (int) ($this->config['timeout'] ?? 90),
            'connect_timeout' => (int) ($this->config['connect_timeout'] ?? 15),
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function search(string $query, ?SearchOptions $options = null): SearchResults
    {
        $options ??= new SearchOptions;

        if ($this->apiKey === '') {
            throw new RuntimeException('SearchAPI.io API key is not configured (SEARCHAPI_API_KEY).');
        }

        $query = trim($query);
        if ($query === '') {
            return new SearchResults(items: [], query: '', totalEstimated: null);
        }

        $items = [];
        $globalIndex = $options->offset;
        $remaining = max(0, $options->limit);
        $totalEstimated = null;

        while ($remaining > 0) {
            $page = intdiv($globalIndex, self::RESULTS_PER_PAGE) + 1;
            $skipInPage = $globalIndex % self::RESULTS_PER_PAGE;

            $payload = $this->fetchPage($query, $page, $options);

            if ($totalEstimated === null && isset($payload['search_information']['total_results'])) {
                $totalEstimated = (int) $payload['search_information']['total_results'];
            }

            $organic = $payload['organic_results'] ?? [];
            if (! is_array($organic) || $organic === []) {
                break;
            }

            $organic = array_values($organic);

            if ($skipInPage >= count($organic)) {
                break;
            }

            for ($i = $skipInPage; $i < count($organic) && $remaining > 0; $i++) {
                $row = $organic[$i];
                if (! is_array($row)) {
                    $globalIndex++;

                    continue;
                }

                $url = (string) ($row['link'] ?? '');
                if ($url === '') {
                    $globalIndex++;

                    continue;
                }

                $items[] = $this->mapOrganicRow($row, $globalIndex);
                $globalIndex++;
                $remaining--;
            }

            if (count($organic) < self::RESULTS_PER_PAGE) {
                break;
            }
        }

        return new SearchResults(
            items: $items,
            query: $query,
            totalEstimated: $totalEstimated,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchPage(string $query, int $page, SearchOptions $options): array
    {
        $queryParams = [
            'engine' => 'google',
            'q' => $query,
            'page' => $page,
            'api_key' => $this->apiKey,
        ];

        if ($options->language !== null) {
            $queryParams['hl'] = $options->language->value;
        }

        if ($options->country !== null) {
            $queryParams['gl'] = strtolower($options->country->value);
        }

        try {
            $response = $this->client->get('api/v1/search', [
                'query' => $queryParams,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('SearchAPI.io request failed: '.$e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status !== 200) {
            throw new RuntimeException("SearchAPI.io returned HTTP {$status}: {$body}");
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            throw new RuntimeException('SearchAPI.io returned invalid JSON.');
        }

        $meta = $data['search_metadata'] ?? [];
        if (($meta['status'] ?? '') !== 'Success') {
            throw new RuntimeException(
                'SearchAPI.io search did not succeed: '.($body !== '' ? $body : json_encode($data))
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function mapOrganicRow(array $row, int $globalIndex): SearchResult
    {
        return new SearchResult(
            title: (string) ($row['title'] ?? ''),
            url: (string) ($row['link'] ?? ''),
            snippet: isset($row['snippet']) ? (string) $row['snippet'] : null,
            position: $globalIndex + 1,
        );
    }
}
