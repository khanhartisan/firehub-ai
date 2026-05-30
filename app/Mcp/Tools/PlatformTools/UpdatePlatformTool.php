<?php

namespace App\Mcp\Tools\PlatformTools;

use App\Enums\PlatformType;
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

#[Description('Update an existing platform.')]
class UpdatePlatformTool extends Tool
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
            'platform_id' => ['required', 'string'],
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('platforms', 'name')->ignore($request->get('platform_id'))],
            'type' => ['sometimes', 'required', Rule::enum(PlatformType::class)],
        ]);

        if (! $request->exists('name') && ! $request->exists('type')) {
            return Response::error('Provide at least one field to update (name or type).');
        }

        /** @var Platform|null $platform */
        $platform = Platform::query()->find($request->get('platform_id'));

        if ($platform === null) {
            return Response::error('Platform not found.');
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

        $data = $platform->toMcpStructuredData();

        return Response::make(Response::text('Successfully updated the platform:'."\n\n".json_encode($data)))
            ->withStructuredContent($data);
    }

    /**
     * Get the tool's input schema.
     *
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
        return !!$request->user()?->is_super;
    }
}
