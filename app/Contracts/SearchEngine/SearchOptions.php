<?php

namespace App\Contracts\SearchEngine;

use App\Enums\Country;
use App\Enums\Language;

/**
 * Parameters for a web search request (pagination, locale, geography).
 */
final readonly class SearchOptions
{
    public function __construct(
        /** Maximum number of organic results to return. */
        public int $limit = 10,
        /** Skip this many results (pagination). */
        public int $offset = 0,
        public ?Language $language = null,
        public ?Country $country = null,
    ) {}
}
