<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools;

use App\Enums\PlatformType;
use App\Facades\Platforms\FlyCms;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\DeleteMenuTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteMenuToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_menu_for_channel_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(DeleteMenuTool::class, [
            'channel_id' => $channel->id,
            'menu_id' => '01J00000000000000000000011',
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully deleted menu [01J00000000000000000000011]')
            ->assertName('platform-manager--flycms--delete-menu-tool')
            ->assertDescription('Delete a FlyCMS menu on the website linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->where('menu_id', '01J00000000000000000000011');
            });

        $this->assertNull(FlyCms::showMenu('01J00000000000000000000011'));
        $this->assertCount(1, FlyCms::listMenus('01J00000000000000000000001'));
    }

    public function test_returns_error_when_menu_does_not_exist(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(DeleteMenuTool::class, [
            'channel_id' => $channel->id,
            'menu_id' => 'unknown-menu-id',
        ]);

        $response->assertHasErrors(['Menu [unknown-menu-id] not found.']);
    }

    public function test_returns_error_when_menu_belongs_to_another_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(DeleteMenuTool::class, [
            'channel_id' => $channel->id,
            'menu_id' => '01J00000000000000000000013',
        ]);

        $response->assertHasErrors(['Menu [01J00000000000000000000013] not found.']);
    }

    public function test_returns_error_when_channel_has_no_website_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(DeleteMenuTool::class, [
            'channel_id' => $channel->id,
            'menu_id' => '01J00000000000000000000011',
        ]);

        $response->assertHasErrors(['Channel '.$channel->id.' does not have a FlyCMS website reference.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(DeleteMenuTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'menu_id' => '01J00000000000000000000011',
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
