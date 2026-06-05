<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\ThemeTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\ThemeTools\ShowThemeTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowThemeToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_theme_for_channel_platform(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ShowThemeTool::class, [
            'channel_id' => $channel->id,
            'theme_id' => '01J00000000000000000000081',
        ]);

        $response
            ->assertOk()
            ->assertSee('Theme details')
            ->assertName('platform-manager--flycms--show-theme-tool')
            ->assertDescription('Show a FlyCMS theme by ID for the platform linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->where('id', '01J00000000000000000000081')
                    ->where('name', 'Good News')
                    ->where('key', 'goodnews')
                    ->where('dev_mode', false)
                    ->where('websites_count', 1)
                    ->has('guidelines')
                    ->etc();
            });
    }

    public function test_validation_fails_when_required_fields_are_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowThemeTool::class, []);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_theme_does_not_exist(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ShowThemeTool::class, [
            'channel_id' => $channel->id,
            'theme_id' => 'unknown-theme-id',
        ]);

        $response->assertHasErrors(['Theme [unknown-theme-id] not found.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ShowThemeTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'theme_id' => '01J00000000000000000000081',
        ]);

        $response->assertHasErrors(['Unauthenticated.']);
    }

    private function attachClient(User $user, string $name): Client
    {
        $client = new Client;
        $client->name = $name;
        $client->save();

        $user->clients()->attach($client);

        return $client;
    }

    private function createPlatform(string $name, PlatformType $type): Platform
    {
        $platform = new Platform;
        $platform->name = $name;
        $platform->type = $type;
        $platform->save();

        return $platform;
    }

    private function createFlyCmsChannel(User $user, string $name): Channel
    {
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($user)->tool(CreateChannelTool::class, [
            'client_id' => $client->id,
            'platform_id' => $platform->id,
            'name' => $name,
        ]);

        $response->assertOk();

        $channel = Channel::query()
            ->where('client_id', $client->id)
            ->where('platform_id', $platform->id)
            ->where('name', $name)
            ->first();

        $this->assertNotNull($channel);

        return $channel;
    }
}
