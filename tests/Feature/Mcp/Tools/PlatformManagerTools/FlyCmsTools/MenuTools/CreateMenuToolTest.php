<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools;

use App\Enums\PlatformType;
use App\Facades\Platforms\FlyCms;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\CreateMenuTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateMenuToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_menu_for_channel_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(CreateMenuTool::class, [
            'channel_id' => $channel->id,
            'create_menu_data' => [
                'key' => 'sidebar',
                'items' => [
                    [
                        'text' => 'Contact',
                        'link' => '/contact',
                        'new_tab' => 0,
                    ],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created a new menu')
            ->assertName('platform-manager--flycms--create-menu-tool')
            ->assertDescription('Create a FlyCMS menu on the website linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->where('key', 'sidebar')
                    ->where('website_id', '01J00000000000000000000001')
                    ->has('items', 1)
                    ->where('items', fn (mixed $items): bool => data_get(
                        json_decode(json_encode($items), true),
                        '0.text'
                    ) === 'Contact' && data_get(
                        json_decode(json_encode($items), true),
                        '0.link'
                    ) === '/contact')
                    ->has('id')
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });

        $this->assertCount(3, FlyCms::listMenus('01J00000000000000000000001'));
    }

    public function test_uses_channel_website_even_when_payload_includes_different_website_id(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(CreateMenuTool::class, [
            'channel_id' => $channel->id,
            'create_menu_data' => [
                'website_id' => '01J00000000000000000000002',
                'key' => 'sidebar',
                'items' => [],
            ],
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json): void {
                $json->where('website_id', '01J00000000000000000000001')
                    ->where('key', 'sidebar')
                    ->etc();
            });
    }

    public function test_returns_error_when_create_menu_data_is_empty(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(CreateMenuTool::class, [
            'channel_id' => $channel->id,
            'create_menu_data' => [],
        ]);

        $response->assertHasErrors(['Provide create_menu_data with at least key.']);
    }

    public function test_returns_error_when_channel_has_no_website_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(CreateMenuTool::class, [
            'channel_id' => $channel->id,
            'create_menu_data' => [
                'key' => 'sidebar',
            ],
        ]);

        $response->assertHasErrors(['Channel '.$channel->id.' does not have a FlyCMS website reference.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(CreateMenuTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'create_menu_data' => [
                'key' => 'sidebar',
            ],
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

    private function createFlyCmsChannel(User $user, string $name, ?string $reference = null): Channel
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

        if ($reference !== null) {
            $channel->reference = $reference;
            $channel->save();
        }

        return $channel;
    }
}
