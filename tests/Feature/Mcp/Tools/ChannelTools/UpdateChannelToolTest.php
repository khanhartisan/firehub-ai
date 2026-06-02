<?php

namespace Tests\Feature\Mcp\Tools\ChannelTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\UpdateChannelTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateChannelToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_channel_name(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $channel = $this->createChannel($client, $platform, 'Old Name');

        $response = AppServer::actingAs($user)->tool(UpdateChannelTool::class, [
            'channel_id' => $channel->id,
            'name' => 'Updated Name',
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the channel')
            ->assertName('update-channel-tool')
            ->assertDescription('Update an existing channel.')
            ->assertStructuredContent(function ($json) use ($channel): void {
                $json->where('id', $channel->id)
                    ->where('name', 'Updated Name')
                    ->etc();
            });

        $channel->refresh();
        $this->assertSame('Updated Name', $channel->name);
    }

    public function test_updates_client_and_config(): void
    {
        $user = User::factory()->create();
        $currentClient = $this->attachClient($user, 'Acme Corp');
        $newClient = $this->attachClient($user, 'Beta Corp');
        $currentPlatform = $this->createPlatform('Old FlyCMS', PlatformType::FLYCMS);
        $channel = $this->createChannel($currentClient, $currentPlatform, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(UpdateChannelTool::class, [
            'channel_id' => $channel->id,
            'client_id' => $newClient->id,
            'config' => ['website_id' => 'site-123'],
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($newClient, $currentPlatform): void {
                $json->where('client_id', $newClient->id)
                    ->where('platform_id', $currentPlatform->id)
                    ->where('config', [])
                    ->etc();
            });

        $channel->refresh();
        $this->assertSame($newClient->id, $channel->client_id);
        $this->assertSame($currentPlatform->id, $channel->platform_id);
        $this->assertNull($channel->config);
    }

    public function test_validation_fails_when_channel_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(UpdateChannelTool::class, [
            'name' => 'Updated Name',
        ]);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_no_fields_to_update_are_provided(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $channel = $this->createChannel($client, $platform, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(UpdateChannelTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response->assertHasErrors(['Provide at least one field to update (name, client_id, or config).']);
    }

    public function test_returns_error_when_channel_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $channel = $this->createChannel($client, $platform, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(UpdateChannelTool::class, [
            'channel_id' => $channel->id,
            'name' => 'Updated Name',
        ]);

        $response->assertHasErrors(['Channel not found or you do not have access to this channel.']);
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

    private function createChannel(Client $client, Platform $platform, string $name): Channel
    {
        $channel = new Channel;
        $channel->client()->associate($client);
        $channel->platform()->associate($platform);
        $channel->name = $name;
        $channel->save();

        return $channel;
    }
}
