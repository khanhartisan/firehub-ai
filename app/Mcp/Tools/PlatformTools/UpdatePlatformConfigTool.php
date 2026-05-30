<?php

namespace App\Mcp\Tools\PlatformTools;

use App\Contracts\Platforms\FlyCms\Config as FlyCmsConfig;
use App\Enums\PlatformType;
use App\Models\Platform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update the configuration of an existing platform.')]
class UpdatePlatformConfigTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory|Response
    {
        $request->validate([
            'platform_id' => ['required', 'string'],
            'flycms_config' => ['sometimes', 'array'],
        ]);

        /** @var Platform|null $platform */
        $platform = Platform::query()->find($request->get('platform_id'));

        if ($platform === null) {
            return Response::error('Platform not found.');
        }

        try {
            $config = $this->resolveConfig($platform, $request);
        } catch (\InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        if ($config === null) {
            return Response::error($this->missingConfigMessage($platform->type));
        }

        $platform->config = $config;

        DB::transaction(function () use ($platform): void {
            $platform->save();
        });

        $platform->refresh();

        $data = $platform->toMcpStructuredData();

        return Response::make(Response::text('Successfully updated the platform config:'."\n\n".json_encode($data)))
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
            'platform_id' => $schema
                ->string()
                ->description('The ULID of the platform to update.')
                ->required(),
            'flycms_config' => $schema
                ->object(new FlyCmsConfig()->toJsonSchema($schema))
                ->nullable()
                ->description('Config object for the case platform type is FLYCMS'),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return !!$request->user()?->is_super;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveConfig(Platform $platform, Request $request): ?array
    {
        return match ($platform->type) {
            PlatformType::FLYCMS => $this->resolveFlyCmsConfig($request),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveFlyCmsConfig(Request $request): ?array
    {
        if (! $request->exists('flycms_config')) {
            return null;
        }

        $rawConfig = $request->get('flycms_config');

        if (! is_array($rawConfig)) {
            throw new \InvalidArgumentException('flycms_config must be an object.');
        }

        return (new FlyCmsConfig($rawConfig))->toArray();
    }

    private function missingConfigMessage(PlatformType $type): string
    {
        return match ($type) {
            PlatformType::FLYCMS => 'Provide flycms_config for this platform.',
        };
    }
}
