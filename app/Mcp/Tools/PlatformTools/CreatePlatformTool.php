<?php

namespace App\Mcp\Tools\PlatformTools;

use App\Enums\PlatformType;
use App\Mcp\Support\McpResponse;
use App\Models\Platform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new platform.')]
class CreatePlatformTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory|Response
    {
        if ($request->has('name')) {
            $request->merge(['name' => trim((string) $request->get('name'))]);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:platforms,name'],
            'type' => ['required', Rule::enum(PlatformType::class)],
        ]);

        $platform = new Platform;
        $platform->name = (string) $request->get('name');
        $platform->type = PlatformType::from((string) $request->get('type'));

        DB::transaction(function () use ($platform): void {
            $platform->save();
        });

        $platform->refresh();

        return McpResponse::created('platform', $platform->toMcpStructuredData());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Unique platform name (for internal display)')
                ->required(),
            'type' => $schema->string()
                ->enum(PlatformType::class)
                ->description('Platform type')
                ->required(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return (bool) $request->user()?->is_super;
    }
}
