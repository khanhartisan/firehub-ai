<?php

namespace App\Contracts\SearchEngine;

use App\Enums\Country;
use App\Enums\Language;

/**
 * Parameters for a web search request (pagination, locale, geography).
 */
final class SearchOptions
{
    protected int $limit = 10;

    protected int $offset = 0;

    protected ?Language $language = null;

    protected ?Country $country = null;

    public static function create(): self
    {
        return new self;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $offset): static
    {
        $this->offset = $offset;

        return $this;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(?Language $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): static
    {
        $this->country = $country;

        return $this;
    }
}
