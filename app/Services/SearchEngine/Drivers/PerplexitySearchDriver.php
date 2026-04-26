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
 * Web search via Perplexity Search API.
 *
 * Uses Perplexity's chat endpoint with search enabled and maps search citations
 * into normalized SearchResult objects.
 */
class PerplexitySearchDriver implements SearchEngine
{
    protected Client $client;

    protected string $apiKey;

    protected string $model;

    public function __construct(
        protected array $config = [],
        ?Client $httpClient = null,
    ) {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'https://api.perplexity.ai'), '/').'/';
        $this->apiKey = (string) ($this->config['api_key'] ?? '');
        $this->model = (string) ($this->config['model'] ?? 'sonar');

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->apiKey !== '') {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        $this->client = $httpClient ?? new Client([
            'base_uri' => $baseUrl,
            'timeout' => (int) ($this->config['timeout'] ?? 90),
            'connect_timeout' => (int) ($this->config['connect_timeout'] ?? 15),
            'headers' => $headers,
        ]);
    }

    public function search(string $query, ?SearchOptions $options = null): SearchResults
    {
        $options ??= new SearchOptions;

        if ($this->apiKey === '') {
            throw new RuntimeException('Perplexity API key is not configured (PERPLEXITY_API_KEY).');
        }

        $query = trim($query);
        if ($query === '') {
            return new SearchResults(items: [], query: '', totalEstimated: null);
        }

        $data = $this->performSearch($query);

        $rawSources = $this->extractSources($data);
        $offset = max(0, $options->getOffset());
        $limit = max(0, $options->getLimit());

        $rows = array_slice($rawSources, $offset, $limit);

        $items = [];
        $globalIndex = $offset;
        foreach ($rows as $row) {
            $result = $this->mapSource($row, $globalIndex);
            if ($result !== null) {
                $items[] = $result;
            }
            $globalIndex++;
        }

        return new SearchResults(
            items: $items,
            query: $query,
            totalEstimated: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function performSearch(string $query): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $query,
                ],
            ],
            'return_citations' => true,
        ];

        try {
            $response = $this->client->post('chat/completions', [
                'json' => $payload,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Perplexity request failed: '.$e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Perplexity returned HTTP {$status}: {$body}");
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            throw new RuntimeException('Perplexity returned invalid JSON.');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    protected function extractSources(array $data): array
    {
        if (isset($data['search_results']) && is_array($data['search_results'])) {
            return array_values(array_filter($data['search_results'], 'is_array'));
        }

        if (isset($data['citations']) && is_array($data['citations'])) {
            $rows = [];
            foreach ($data['citations'] as $citation) {
                if (is_string($citation) && $citation !== '') {
                    $rows[] = ['url' => $citation];
                }
            }

            return $rows;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function mapSource(array $row, int $globalIndex): ?SearchResult
    {
        $url = (string) ($row['url'] ?? $row['link'] ?? '');
        if ($url === '') {
            return null;
        }

        return new SearchResult(
            title: (string) ($row['title'] ?? $url),
            url: $url,
            snippet: isset($row['snippet']) ? (string) $row['snippet'] : null,
            position: $globalIndex + 1,
        );
    }
}
