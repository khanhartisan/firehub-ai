<?php

namespace App\Contracts\Scraper;

use Psr\Http\Message\ResponseInterface;

/**
 * Fetches a URL and returns a PSR-7 response (e.g. HTML body).
 *
 * Default implementation uses Guzzle. Used by ScrapeEntityJob to fetch page content
 * before classification, parsing, and snapshot storage.
 */
interface Scraper
{
    /**
     * Perform an HTTP GET request and return the response.
     *
     * @param  string  $url  Absolute URL to fetch
     * @param  ScrapingOptions|null  $options  Optional (e.g. country for geo/proxy)
     * @return ResponseInterface  PSR-7 response; body typically HTML
     */
    public function fetch(string $url, ?ScrapingOptions $options = null): ResponseInterface;
}