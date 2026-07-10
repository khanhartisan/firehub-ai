<?php

namespace App\Mcp\Tools\GuidelineTools;

use App\Mcp\Support\Guidelines\GuidelineCatalog;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List all available overview/guideline documents and their resource URIs so clients can fetch docs via tools even when resources/read is unavailable.')]
class ListGuidelinesTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $search = trim((string) $request->get('search', ''));
        $type = trim((string) $request->get('type', ''));

        $items = collect(GuidelineCatalog::all())
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($search !== '', function ($query) use ($search) {
                $needle = strtolower($search);

                return $query->filter(function (array $item) use ($needle): bool {
                    return str_contains(strtolower($item['title']), $needle)
                        || str_contains(strtolower($item['uri']), $needle)
                        || str_contains(strtolower($item['name']), $needle);
                });
            })
            ->sortBy('title')
            ->values()
            ->all();

        return McpResponse::list(
            'guideline resource',
            $items,
            'guideline_resources',
            'Available guideline resources:',
        );
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Optional case-insensitive filter by title, URI, or resource name'),
            'type' => $schema->string()
                ->enum(['overview', 'guideline'])
                ->description('Optional filter by document type'),
        ];
    }
}
