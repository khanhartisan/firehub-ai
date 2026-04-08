<?php

namespace App\Contracts\SearchEngine;

/**
 * Web search (SERP) provider: runs a query and returns ranked links/snippets.
 *
 * Implementations may call HTTP APIs, render search pages, or proxy third-party services.
 */
interface SearchEngine
{
    public function search(string $query, ?SearchOptions $options = null): SearchResults;
}
