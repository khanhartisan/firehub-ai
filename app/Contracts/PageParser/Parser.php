<?php

namespace App\Contracts\PageParser;

/**
 * Parses scraped HTML into structured page data (title, excerpt, markdown, links).
 *
 * Used after fetch in ScrapeEntityJob; PageData provides excerpt for the entity,
 * markdown for storage, and linked URLs for discovering new entities to scrape.
 * Implementations typically use an AI API (e.g. OpenAI).
 */
interface Parser
{
    /**
     * Extract structured data and markdown from HTML.
     *
     * @param  string  $html  Sanitized or cleaned HTML (e.g. from HtmlCleaner)
     * @return PageData  Title, excerpt, markdown, dates, canonical, linked URLs
     */
    public function parse(string $html): PageData;
}