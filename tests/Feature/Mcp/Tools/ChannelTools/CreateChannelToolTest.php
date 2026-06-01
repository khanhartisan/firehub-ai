<?php

namespace Tests\Feature\Mcp\Tools\ChannelTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateChannelToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_channel_with_required_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $name = 'Main Blog';

        $response = AppServer::actingAs($user)->tool(CreateChannelTool::class, [
            'client_id' => $client->id,
            'platform_id' => $platform->id,
            'name' => $name,
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created a new channel')
            ->assertName('create-channel-tool')
            ->assertDescription('Create a new publishing channel for a client on a platform.')
            ->assertStructuredContent(function ($json) use ($client, $platform, $name): void {
                $json->where('client_id', $client->id)
                    ->where('platform_id', $platform->id)
                    ->where('name', $name)
                    ->where('publications_count', 0)
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });

        $this->assertDatabaseHas('channels', [
            'client_id' => $client->id,
            'platform_id' => $platform->id,
            'name' => $name,
        ]);
    }

    public function test_creates_channel_with_config(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $config = ['website_id' => 'site-123'];

        $response = AppServer::actingAs($user)->tool(CreateChannelTool::class, [
            'client_id' => $client->id,
            'platform_id' => $platform->id,
            'name' => 'Main Blog',
            'config' => $config,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($config): void {
                $json->where('config', $config)->etc();
            });

        $channel = Channel::query()->first();
        $this->assertNotNull($channel);
        $this->assertSame($config, $channel->config);
    }

    public function test_validation_fails_when_client_id_is_missing(): void
    {
        $user = User::factory()->create();
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($user)->tool(CreateChannelTool::class, [
            'platform_id' => $platform->id,
            'name' => 'Main Blog',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_validation_fails_when_platform_id_is_missing(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(CreateChannelTool::class, [
            'client_id' => $client->id,
            'name' => 'Main Blog',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_validation_fails_when_name_is_missing(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($user)->tool(CreateChannelTool::class, [
            'client_id' => $client->id,
            'platform_id' => $platform->id,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_returns_error_when_name_is_blank(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($user)->tool(CreateChannelTool::class, [
            'client_id' => $client->id,
            'platform_id' => $platform->id,
            'name' => '   ',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_returns_error_when_client_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($user)->tool(CreateChannelTool::class, [
            'client_id' => $client->id,
            'platform_id' => $platform->id,
            'name' => 'Main Blog',
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_returns_error_when_platform_does_not_exist(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(CreateChannelTool::class, [
            'client_id' => $client->id,
            'platform_id' => '01J0000000000000000000000',
            'name' => 'Main Blog',
        ]);

        $response->assertHasErrors(['Platform not found.']);

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(CreateChannelTool::class, [
            'client_id' => '01J0000000000000000000000',
            'platform_id' => '01J0000000000000000000001',
            'name' => 'Main Blog',
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
}
