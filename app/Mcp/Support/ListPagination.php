<?php

namespace App\Mcp\Support;

final readonly class ListPagination
{
    public function __construct(
        public int $page,
        public int $perPage,
    ) {}

    public function listMessageSuffix(): string
    {
        return ' (page '.$this->page.', per_page '.$this->perPage.')';
    }
}
