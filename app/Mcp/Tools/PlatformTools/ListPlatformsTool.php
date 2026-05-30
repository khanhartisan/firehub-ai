<?php

namespace App\Mcp\Tools\PlatformTools;

use App\Models\Platform;
use App\Utils\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show the list of available platforms.')]
class ListPlatformsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        /** @var Collection<int, Platform> $platforms */
        $platforms = Platform::query()
            ->orderBy('name')
            ->get();

        if ($platforms->isEmpty()) {
            return Response::error('No platforms found.');
        }

        $platformsData = $platforms
            ->map(fn (Platform $platform) => $platform->toMcpStructuredData())
            ->values()
            ->toArray();

        return Response::make(
            Response::text('Found '.$platforms->count().' '.Str::plural('platform', $platforms->count()).":\n\n".json_encode($platformsData))
        )->withStructuredContent([
            'platforms' => $platformsData,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            //
        ];
    }
}
