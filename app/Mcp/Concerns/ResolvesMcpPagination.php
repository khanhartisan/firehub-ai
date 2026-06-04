<?php

namespace App\Mcp\Concerns;

use App\Mcp\Support\ListPagination;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

trait ResolvesMcpPagination
{
    protected function defaultListLimit(): int
    {
        return 100;
    }

    protected function maxListLimit(): int
    {
        return 100;
    }

    protected function resolvePagination(Request $request): ListPagination
    {
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(
            $this->maxListLimit(),
            max(1, (int) $request->integer('per_page', $this->defaultListLimit()))
        );

        return new ListPagination(page: $page, perPage: $perPage);
    }

    /**
     * @return array<string, JsonSchema>
     */
    protected function paginationSchemaProperties(JsonSchema $schema, string $itemLabel): array
    {
        $default = $this->defaultListLimit();
        $max = $this->maxListLimit();

        return [
            'page' => $schema->integer()
                ->description('Page number (1-based, default: 1)')
                ->min(1),
            'per_page' => $schema->integer()
                ->description("Maximum {$itemLabel} per page (default: {$default}, max: {$max})")
                ->min(1)
                ->max($max),
        ];
    }

}
