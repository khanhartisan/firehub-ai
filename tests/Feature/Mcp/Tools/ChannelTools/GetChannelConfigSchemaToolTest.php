<?php

namespace Tests\Feature\Mcp\Tools\ChannelTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\GetChannelConfigSchemaTool;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetChannelConfigSchemaToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_channel_config_schema_for_platform(): void
    {
        $user = User::factory()->create();
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($user)->tool(GetChannelConfigSchemaTool::class, [
            'platform_id' => $platform->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Channel config schema details:')
            ->assertName('get-channel-config-schema-tool')
            ->assertDescription('Get the channel config schema for a platform.')
            ->assertStructuredContent(function ($json) use ($platform): void {
                $json->where('platform_id', $platform->id)
                    ->where('platform_type', PlatformType::FLYCMS->value)
                    ->where('channel_config_schema', [])
                    ->etc();
            });
    }

    public function test_validation_fails_when_platform_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(GetChannelConfigSchemaTool::class, []);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_platform_does_not_exist(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(GetChannelConfigSchemaTool::class, [
            'platform_id' => '01J0000000000000000000000',
        ]);

        $response->assertHasErrors(['Platform not found.']);
    }

    private function createPlatform(string $name, PlatformType $type): Platform
    {
        $platform = new Platform;
        $platform->name = $name;
        $platform->type = $type;
        $platform->save();

        return $platform;
    }
}
