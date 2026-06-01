<?php

namespace App\Mcp\Tools\PlatformTools;

use App\Enums\PlatformType;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpResponse;
use App\Models\Platform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an existing platform.')]
class UpdatePlatformTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        if ($request->has('name')) {
            $request->merge(['name' => trim((string) $request->get('name'))]);
        }

        $request->validate([
            'platform_id' => ['required', 'string'],
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('platforms', 'name')->ignore($request->get('platform_id'))],
            'type' => ['sometimes', 'required', Rule::enum(PlatformType::class)],
        ]);

        if (! $request->exists('name') && ! $request->exists('type')) {
            throw new McpToolException('Provide at least one field to update (name or type).');
        }

        /** @var Platform|null $platform */
        $platform = Platform::query()->find($request->get('platform_id'));

        if ($platform === null) {
            throw new McpToolException('Platform not found.');
        }

        if ($request->exists('name')) {
            $platform->name = (string) $request->get('name');
        }

        if ($request->exists('type')) {
            $platform->type = PlatformType::from((string) $request->get('type'));
        }

        DB::transaction(function () use ($platform): void {
            $platform->save();
        });

        $platform->refresh();

        return McpResponse::updated('platform', $platform->toMcpStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'platform_id' => $schema->string()
                ->description('The ULID of the platform to update')
                ->required(),
            'name' => $schema->string()
                ->description('Unique platform name (for internal display)'),
            'type' => $schema->string()
                ->enum(PlatformType::class)
                ->description('Platform type'),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return (bool) $request->user()?->is_super;
    }
}
