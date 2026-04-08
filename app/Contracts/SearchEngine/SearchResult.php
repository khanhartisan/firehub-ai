<?php

namespace App\Contracts\SearchEngine;

use App\Contracts\Serializable;

/**
 * One organic result line from a search engine results page.
 */
final readonly class SearchResult implements Serializable
{
    use \App\Concerns\Serializable;

    public function __construct(
        public string $title,
        /** Resolved destination URL for the result. */
        public string $url,
        public ?string $snippet = null,
        /** 1-based position on the result page, if known. */
        public ?int $position = null,
    ) {}

    /**
     * @param  array{title: string, url: string, snippet?: string|null, position?: int|null}  $data
     */
    public static function fromArray(array $data): static
    {
        return new self(
            title: $data['title'],
            url: $data['url'],
            snippet: $data['snippet'] ?? null,
            position: isset($data['position']) ? (int) $data['position'] : null,
        );
    }

    /**
     * @return array{title: string, url: string, snippet: string|null, position: int|null}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'snippet' => $this->snippet,
            'position' => $this->position,
        ];
    }
}
