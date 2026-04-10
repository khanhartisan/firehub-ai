<?php

namespace App\Services\IntentResolver;

use App\Utils\HtmlCleaner;

/**
 * Shared content preparation for intent resolution drivers.
 */
abstract class IntentResolverService
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_content_length' => 50000,
        ], $config);
    }

    /**
     * Normalize content: strip HTML when present, collapse whitespace, truncate.
     */
    protected function prepareContent(string $content): string
    {
        $maxLength = (int) ($this->config['max_content_length'] ?? 50000);

        if (strip_tags($content) !== $content) {
            $content = HtmlCleaner::clean($content, $maxLength);
            $content = strip_tags($content);
        }

        $content = preg_replace('/\s+/', ' ', trim($content)) ?? '';

        if (strlen($content) > $maxLength) {
            $content = substr($content, 0, $maxLength);
        }

        return $content;
    }
}
