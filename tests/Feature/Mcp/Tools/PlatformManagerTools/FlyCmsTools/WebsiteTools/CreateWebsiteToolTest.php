<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools;

use App\Enums\PlatformType;
use App\Facades\Platforms\FlyCms;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\CreateWebsiteTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateWebsiteToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_website_and_saves_channel_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');
        $createWebsiteData = [
            'status' => 'active',
            'name' => 'New Site',
            'post_route' => '/posts/{post}',
        ];

        $response = AppServer::actingAs($user)->tool(CreateWebsiteTool::class, [
            'channel_id' => $channel->id,
            'create_website_data' => $createWebsiteData,
        ]);

        $response
            ->assertOk()
            ->assertSee('Website details')
            ->assertName('platform-manager--flycms--create-website-tool')
            ->assertDescription('Create a website in the FlyCms platform representing for the given channel.')
            ->assertStructuredContent(function ($json) use ($createWebsiteData): void {
                $json->where('name', $createWebsiteData['name'])
                    ->where('status', $createWebsiteData['status'])
                    ->where('post_route', $createWebsiteData['post_route'])
                    ->where('domains_count', 0)
                    ->where('public_posts_count', 0)
                    ->has('id')
                    ->has('created_at')
                    ->has('updated_at')
                    ->has('meta')
                    ->etc();
            });

        $channel->refresh();

        $this->assertNotNull($channel->reference);
        $this->assertSame(
            $channel->reference,
            FlyCms::showWebsite($channel->reference)?->getData()['id']
        );
    }

    public function test_returns_existing_website_when_channel_already_has_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Existing Blog');
        $existingWebsiteId = '01J00000000000000000000001';
        $channel->reference = $existingWebsiteId;
        $channel->save();

        $response = AppServer::actingAs($user)->tool(CreateWebsiteTool::class, [
            'channel_id' => $channel->id,
            'create_website_data' => [
                'status' => 'active',
                'name' => 'Should Not Be Created',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Website details')
            ->assertStructuredContent(function ($json) use ($existingWebsiteId): void {
                $json->where('id', $existingWebsiteId)
                    ->where('name', 'Sample Blog')
                    ->where('status', 'active')
                    ->etc();
            });

        $channel->refresh();
        $this->assertSame($existingWebsiteId, $channel->reference);
    }

    public function test_creates_website_when_channel_reference_points_to_missing_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Stale Reference Blog');
        $channel->reference = 'missing-website-id';
        $channel->save();

        $response = AppServer::actingAs($user)->tool(CreateWebsiteTool::class, [
            'channel_id' => $channel->id,
            'create_website_data' => [
                'status' => 'active',
                'name' => 'Replacement Site',
            ],
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json): void {
                $json->where('name', 'Replacement Site')
                    ->where('status', 'active')
                    ->etc();
            });

        $channel->refresh();

        $this->assertNotSame('missing-website-id', $channel->reference);
        $this->assertNotNull(FlyCms::showWebsite($channel->reference));
    }

    public function test_validation_fails_when_channel_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(CreateWebsiteTool::class, [
            'create_website_data' => [
                'status' => 'active',
                'name' => 'New Site',
            ],
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_create_website_data_is_missing(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(CreateWebsiteTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_channel_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $channel = $this->createFlyCmsChannel($otherUser, 'Other Channel');

        $response = AppServer::actingAs($user)->tool(CreateWebsiteTool::class, [
            'channel_id' => $channel->id,
            'create_website_data' => [
                'status' => 'active',
                'name' => 'New Site',
            ],
        ]);

        $response->assertHasErrors(['Channel not found or you do not have access to this channel.']);
    }

    public function test_returns_error_when_channel_does_not_exist(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(CreateWebsiteTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'create_website_data' => [
                'status' => 'active',
                'name' => 'New Site',
            ],
        ]);

        $response->assertHasErrors(['Channel not found or you do not have access to this channel.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(CreateWebsiteTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'create_website_data' => [
                'status' => 'active',
                'name' => 'New Site',
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
