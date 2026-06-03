<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\ShowWebsiteTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowWebsiteToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_website_linked_to_channel(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ShowWebsiteTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Website details')
            ->assertName('platform-manager--flycms--show-website-tool')
            ->assertDescription('Show the FlyCMS website linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->where('id', '01J00000000000000000000001')
                    ->where('name', 'Sample Blog')
                    ->where('status', 'active')
                    ->where('domains_count', 2)
                    ->where('public_posts_count', 42)
                    ->has('asset_route')
                    ->has('created_at')
                    ->has('updated_at')
                    ->has('meta')
                    ->etc();
            });
    }

    public function test_validation_fails_when_channel_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowWebsiteTool::class, []);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_channel_has_no_website_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ShowWebsiteTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response->assertHasErrors(['Channel '.$channel->id.' does not have a FlyCMS website reference.']);
    }

    public function test_returns_error_when_channel_reference_points_to_missing_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', 'missing-website-id');

        $response = AppServer::actingAs($user)->tool(ShowWebsiteTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response->assertHasErrors(['Website [missing-website-id] not found.']);
    }

    public function test_returns_error_when_channel_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $channel = $this->createFlyCmsChannel($otherUser, 'Other Channel', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ShowWebsiteTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response->assertHasErrors(['Channel not found or you do not have access to this channel.']);
    }

    public function test_returns_error_when_channel_does_not_exist(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowWebsiteTool::class, [
            'channel_id' => '01J0000000000000000000000',
        ]);

        $response->assertHasErrors(['Channel not found or you do not have access to this channel.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ShowWebsiteTool::class, [
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
