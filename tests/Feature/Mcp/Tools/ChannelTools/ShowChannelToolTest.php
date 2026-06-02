<?php

namespace Tests\Feature\Mcp\Tools\ChannelTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\ChannelTools\ShowChannelTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowChannelToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_channel_details(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('FlyCMS', PlatformType::FLYCMS);
        $channel = $this->createChannel($user, $client, $platform, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ShowChannelTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Channel details')
            ->assertName('show-channel-tool')
            ->assertDescription('Show details of an existing channel.')
            ->assertStructuredContent(function ($json) use ($channel, $client, $platform): void {
                $json->where('id', $channel->id)
                    ->where('client_id', $client->id)
                    ->where('platform_id', $platform->id)
                    ->where('name', 'Main Blog')
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });
    }

    public function test_validation_fails_when_channel_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowChannelTool::class, []);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_channel_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherClient = $this->attachClient($otherUser, 'Other Client');
        $platform = $this->createPlatform('FlyCMS', PlatformType::FLYCMS);
        $channel = $this->createChannel($otherUser, $otherClient, $platform, 'Other Channel');

        $response = AppServer::actingAs($user)->tool(ShowChannelTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response->assertHasErrors(['Channel not found or you do not have access to this channel.']);
    }

    public function test_returns_error_when_channel_does_not_exist(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowChannelTool::class, [
            'channel_id' => '01J0000000000000000000000',
        ]);

        $response->assertHasErrors(['Channel not found or you do not have access to this channel.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ShowChannelTool::class, [
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

    private function createChannel(User $user, Client $client, Platform $platform, string $name): Channel
    {
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
