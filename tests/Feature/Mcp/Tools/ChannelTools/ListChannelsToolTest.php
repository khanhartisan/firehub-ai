<?php

namespace Tests\Feature\Mcp\Tools\ChannelTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\ChannelTools\ListChannelsTool;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListChannelsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_channels_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('FlyCMS', PlatformType::FLYCMS);
        $this->createChannel($user, $client, $platform, 'Main Blog');
        $this->createChannel($user, $client, $platform, 'News');

        $response = AppServer::actingAs($user)->tool(ListChannelsTool::class);

        $response
            ->assertOk()
            ->assertSee('Showing 2 channels')
            ->assertName('list-channels-tool')
            ->assertDescription('Show the list of channels that belong to the current user\'s clients.')
            ->assertStructuredContent(function ($json): void {
                $json->has('channels', 2)
                    ->where('pagination.current_page', 1)
                    ->where('pagination.per_page', 15)
                    ->where('pagination.total', 2)
                    ->where('pagination.last_page', 1)
                    ->etc();
            });
    }

    public function test_filters_channels_by_client_id(): void
    {
        $user = User::factory()->create();
        $firstClient = $this->attachClient($user, 'Acme Corp');
        $secondClient = $this->attachClient($user, 'Global Media');
        $platform = $this->createPlatform('FlyCMS', PlatformType::FLYCMS);
        $this->createChannel($user, $firstClient, $platform, 'Acme Channel');
        $this->createChannel($user, $secondClient, $platform, 'Global Channel');

        $response = AppServer::actingAs($user)->tool(ListChannelsTool::class, [
            'client_id' => $firstClient->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Showing 1 channel')
            ->assertStructuredContent(function ($json) use ($firstClient): void {
                $json->has('channels', 1)
                    ->where('channels.0.client_id', $firstClient->id)
                    ->where('channels.0.name', 'Acme Channel')
                    ->etc();
            });
    }

    public function test_filters_channels_by_platform_id(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $firstPlatform = $this->createPlatform('FlyCMS One', PlatformType::FLYCMS);
        $secondPlatform = $this->createPlatform('FlyCMS Two', PlatformType::FLYCMS);
        $this->createChannel($user, $client, $firstPlatform, 'Channel One');
        $this->createChannel($user, $client, $secondPlatform, 'Channel Two');

        $response = AppServer::actingAs($user)->tool(ListChannelsTool::class, [
            'platform_id' => $firstPlatform->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Showing 1 channel')
            ->assertStructuredContent(function ($json) use ($firstPlatform): void {
                $json->has('channels', 1)
                    ->where('channels.0.platform_id', $firstPlatform->id)
                    ->where('channels.0.name', 'Channel One')
                    ->etc();
            });
    }

    public function test_returns_error_when_no_channels_found(): void
    {
        $user = User::factory()->create();
        $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(ListChannelsTool::class);

        $response->assertHasErrors(['No channels found.']);
    }

    public function test_returns_error_when_filtering_by_inaccessible_client(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherClient = $this->attachClient($otherUser, 'Other Client');

        $response = AppServer::actingAs($user)->tool(ListChannelsTool::class, [
            'client_id' => $otherClient->id,
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ListChannelsTool::class);

        $response->assertHasErrors(['Unauthenticated.']);
    }

    public function test_paginates_channels(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $platform = $this->createPlatform('FlyCMS', PlatformType::FLYCMS);

        $this->createChannel($user, $client, $platform, 'Channel A');
        $this->createChannel($user, $client, $platform, 'Channel B');
        $this->createChannel($user, $client, $platform, 'Channel C');

        $firstPage = AppServer::actingAs($user)->tool(ListChannelsTool::class, [
            'per_page' => 2,
            'page' => 1,
        ]);

        $firstPage
            ->assertOk()
            ->assertSee('page 1 of 2')
            ->assertStructuredContent(function ($json): void {
                $json->has('channels', 2)
                    ->where('pagination.current_page', 1)
                    ->where('pagination.per_page', 2)
                    ->where('pagination.total', 3)
                    ->where('pagination.last_page', 2)
                    ->etc();
            });

        $secondPage = AppServer::actingAs($user)->tool(ListChannelsTool::class, [
            'per_page' => 2,
            'page' => 2,
        ]);

        $secondPage
            ->assertOk()
            ->assertSee('page 2 of 2')
            ->assertStructuredContent(function ($json): void {
                $json->has('channels', 1)
                    ->where('pagination.current_page', 2)
                    ->where('pagination.total', 3)
                    ->etc();
            });
    }

    public function test_validation_fails_when_page_is_invalid(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ListChannelsTool::class, [
            'page' => 0,
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_per_page_exceeds_maximum(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ListChannelsTool::class, [
            'per_page' => 101,
        ]);

        $response->assertHasErrors();
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

    private function createChannel(User $user, Client $client, Platform $platform, string $name): void
    {
        $response = AppServer::actingAs($user)->tool(CreateChannelTool::class, [
            'client_id' => $client->id,
            'platform_id' => $platform->id,
            'name' => $name,
        ]);

        $response->assertOk();
    }
}
