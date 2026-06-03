<?php

namespace Tests\Feature\Mcp\Tools\PlatformTools;

use App\Contracts\PlatformManager\FlyCms\Config as FlyCmsConfig;
use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\PlatformTools\UpdatePlatformConfigTool;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatePlatformConfigToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_flycms_platform_config(): void
    {
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformConfigTool::class, [
            'platform_id' => $platform->id,
            'flycms_config' => [
                'base_url' => 'https://flycms.example.test',
                'api_key' => 'secret-api-key',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the platform config')
            ->assertName('update-platform-config-tool')
            ->assertDescription('Update the configuration of an existing platform.')
            ->assertStructuredContent(function ($json) use ($platform): void {
                $json->where('id', $platform->id)
                    ->where('name', 'Production FlyCMS')
                    ->etc();
            });

        $platform->refresh();

        $this->assertInstanceOf(FlyCmsConfig::class, $platform->config);
        $this->assertSame('https://flycms.example.test', $platform->config->getBaseUrl());
        $this->assertSame('secret-api-key', $platform->config->getApiKey());
    }

    public function test_validation_fails_when_platform_id_is_missing(): void
    {
        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformConfigTool::class, [
            'flycms_config' => [
                'base_url' => 'https://flycms.example.test',
                'api_key' => 'secret-api-key',
            ],
        ]);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_platform_is_not_found(): void
    {
        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformConfigTool::class, [
            'platform_id' => '01J0000000000000000000000',
            'flycms_config' => [
                'base_url' => 'https://flycms.example.test',
                'api_key' => 'secret-api-key',
            ],
        ]);

        $response->assertHasErrors(['Platform not found.']);
    }

    public function test_returns_error_when_flycms_config_is_missing_for_flycms_platform(): void
    {
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformConfigTool::class, [
            'platform_id' => $platform->id,
        ]);

        $response->assertHasErrors(['Provide flycms_config for this platform.']);

        $platform->refresh();
        $this->assertNull($platform->config);
    }

    public function test_tool_is_not_available_for_non_super_user(): void
    {
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($this->regularUser())->tool(UpdatePlatformConfigTool::class, [
            'platform_id' => $platform->id,
            'flycms_config' => [
                'base_url' => 'https://flycms.example.test',
                'api_key' => 'secret-api-key',
            ],
        ]);

        $response->assertHasErrors(['Tool [update-platform-config-tool] not found.']);

        $platform->refresh();
        $this->assertNull($platform->config);
    }

    private function createPlatform(string $name, PlatformType $type): Platform
    {
        $platform = new Platform;
        $platform->name = $name;
        $platform->type = $type;
        $platform->save();

        return $platform;
    }

    private function superUser(): User
    {
        $user = User::factory()->create();
        $user->is_super = true;
        $user->save();

        return $user;
    }

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $user->is_super = false;
        $user->save();

        return $user;
    }
}
