<?php

namespace App\Mcp\Tools\PlatformTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpResponse;
use App\Models\Platform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show the list of available platforms.')]
class ListPlatformsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        /** @var Collection<int, Platform> $platforms */
        $platforms = Platform::query()
            ->orderBy('name')
            ->get();

        if ($platforms->isEmpty()) {
            throw new McpToolException('No platforms found.');
        }

        $platformsData = $platforms
            ->map(fn (Platform $platform) => $platform->toMcpStructuredData())
            ->values()
            ->toArray();

        return McpResponse::list('platform', $platformsData, 'platforms');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            //
        ];
    }
}
