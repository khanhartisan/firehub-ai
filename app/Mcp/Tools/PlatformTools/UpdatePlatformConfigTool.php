<?php

namespace App\Mcp\Tools\PlatformTools;

use App\Contracts\PlatformManager\FlyCms\Config as FlyCmsConfig;
use App\Enums\PlatformType;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpResponse;
use App\Models\Platform;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update the configuration of an existing platform.')]
class UpdatePlatformConfigTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'platform_id' => ['required', 'string'],
            'flycms_config' => ['sometimes', 'array'],
        ]);

        /** @var Platform|null $platform */
        $platform = Platform::query()->find($request->get('platform_id'));

        if ($platform === null) {
            throw new McpToolException('Platform not found.');
        }

        try {
            $config = $this->resolveConfig($platform, $request);
        } catch (\InvalidArgumentException $exception) {
            throw new McpToolException($exception->getMessage(), previous: $exception);
        }

        if ($config === null) {
            throw new McpToolException($this->missingConfigMessage($platform->type));
        }

        $platform->config = $config;

        DB::transaction(function () use ($platform): void {
            $platform->save();
        });

        $platform->refresh();

        return McpResponse::updated('platform config', $platform->toMcpStructuredData());
    }

    /**
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
        return (bool) $request->user()?->is_super;
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
