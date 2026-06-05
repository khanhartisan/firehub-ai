<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\ThemeTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\ThemeTools\ListThemesTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListThemesToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_themes_for_channel_platform(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ListThemesTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 3 themes')
            ->assertName('platform-manager--flycms--list-themes-tool')
            ->assertDescription('List FlyCMS themes available on the platform linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->has('themes', 3)
                    ->where('themes.0.name', 'Good News')
                    ->where('themes.0.key', 'goodnews')
                    ->where('themes.1.name', 'Storefront')
                    ->where('themes.1.dev_mode', true)
                    ->where('themes.2.name', 'Minimal');
            });
    }

    public function test_filters_themes_by_search(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ListThemesTool::class, [
            'channel_id' => $channel->id,
            'theme_filter' => [
                'search' => 'store',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 1 theme')
            ->assertStructuredContent(function ($json): void {
                $json->has('themes', 1)
                    ->where('themes.0.name', 'Storefront')
                    ->etc();
            });
    }

    public function test_filters_themes_by_ids(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ListThemesTool::class, [
            'channel_id' => $channel->id,
            'theme_filter' => [
                'ids' => '01J00000000000000000000081,01J00000000000000000000083',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 2 themes')
            ->assertStructuredContent(function ($json): void {
                $json->has('themes', 2)
                    ->where('themes.0.name', 'Good News')
                    ->where('themes.1.name', 'Minimal');
            });
    }

    public function test_supports_pagination(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ListThemesTool::class, [
            'channel_id' => $channel->id,
            'page' => 1,
            'per_page' => 1,
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 1 theme')
            ->assertStructuredContent(function ($json): void {
                $json->has('themes', 1);
            });
    }

    public function test_returns_error_when_no_themes_match(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ListThemesTool::class, [
            'channel_id' => $channel->id,
            'theme_filter' => [
                'search' => 'nonexistent-theme',
            ],
        ]);

        $response->assertHasErrors(['No themes found.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ListThemesTool::class, [
            'channel_id' => '01J0000000000000000000000',
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
