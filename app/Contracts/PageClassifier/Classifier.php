<?php

namespace App\Contracts\PageClassifier;

/**
 * Classifies scraped HTML into page type, content type, temporal, language, and tags.
 *
 * Used after fetch in ScrapePageJob; result is stored on the page and used
 * by the policy engine. Implementations typically call an AI API (e.g. OpenAI).
 */
interface Classifier
{
    /**
     * Analyze HTML and return classification (page type, content type, temporal, language, tags).
     *
     * @param  string  $html  Sanitized or cleaned HTML (e.g. from HtmlCleaner)
     * @return ClassificationResult  Page type, content type, temporal, language, tags
     */
    public function classify(string $html): ClassificationResult;
}