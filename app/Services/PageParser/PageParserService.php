<?php

namespace App\Services\PageParser;

use App\Contracts\PageParser\PageData;
use App\Contracts\PageParser\Parser;
use App\Utils\HtmlCleaner;

abstract class PageParserService implements Parser
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Parse a page from HTML content.
     */
    public function parse(string $html): PageData
    {
        // Extract linked URLs from original HTML before cleaning/truncation
        $linkedUrls = $this->extractLinkedUrls($html);

        // Clean and prepare HTML
        $preparedHtml = $this->prepareHtml($html);

        // Perform parsing - this is implemented by child classes
        $pageData = $this->performParsing($preparedHtml);

        // Set linked page URLs and fetchedAt timestamp
        $pageData->setLinkedPageUrls($linkedUrls);
        $pageData->setFetchedAt(now());

        return $pageData;
    }

    /**
     * Prepare HTML for parsing.
     * Child classes can override this to customize HTML preparation.
     */
    protected function prepareHtml(string $html): string
    {
        $maxLength = $this->config['max_html_length'] ?? 100000;

        return HtmlCleaner::clean($html, $maxLength);
    }

    /**
     * Perform the actual parsing.
     * This method must be implemented by child classes.
     */
    abstract protected function performParsing(string $html): PageData;

    /**
     * Extract linked page URLs from HTML content.
     *
     * @param string $html The HTML content to extract URLs from
     * @return array<int, string> Array of unique URLs found in the HTML
     */
    protected function extractLinkedUrls(string $html): array
    {
        $urls = [];
        
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        
        // Clear libxml errors
        libxml_clear_errors();
        
        $links = $dom->getElementsByTagName('a');
        
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            
            if (empty($href)) {
                continue;
            }
            
            // Skip anchors and javascript/data URLs
            if (str_starts_with($href, '#') || 
                str_starts_with($href, 'javascript:') || 
                str_starts_with($href, 'data:')) {
                continue;
            }
            
            // Normalize the URL and add to array if not already present
            $normalizedUrl = trim($href);
            if (!empty($normalizedUrl) && !in_array($normalizedUrl, $urls, true)) {
                $urls[] = $normalizedUrl;
            }
        }
        
        return array_values(array_slice($urls, 0, 1000));
    }
}
