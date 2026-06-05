<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\Concerns\CreatesFlyCmsFilesForUser;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\ListFilesTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListFilesToolTest extends TestCase
{
    use CreatesFlyCmsFilesForUser;
    use RefreshDatabase;

    public function test_lists_files_for_channel_platform(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'hero-banner', 'code' => 'hero-banner']);
        $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'weekend-ideas', 'ext' => 'webp']);
        $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'storefront-intro', 'ext' => 'mp4', 'code' => 'storefront-intro']);

        $response = AppServer::actingAs($user)->tool(ListFilesTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 3 files')
            ->assertName('platform-manager--flycms--list-files-tool')
            ->assertDescription('List FlyCMS files for the platform linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->has('files', 3)
                    ->where('files.0.code', 'hero-banner')
                    ->where('files.0.type', 'image')
                    ->has('files.1.id')
                    ->has('files.2.id');
            });
    }

    public function test_filters_files_by_type(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'hero-banner', 'code' => 'hero-banner']);
        $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'weekend-ideas', 'ext' => 'webp']);
        $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'storefront-intro', 'ext' => 'mp4', 'code' => 'storefront-intro']);

        $response = AppServer::actingAs($user)->tool(ListFilesTool::class, [
            'channel_id' => $channel->id,
            'file_filter' => [
                'type' => 'image',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 2 files')
            ->assertStructuredContent(function ($json): void {
                $json->has('files', 2)
                    ->where('files.0.type', 'image')
                    ->where('files.1.type', 'image');
            });
    }

    public function test_filters_files_by_code(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'storefront-intro', 'ext' => 'mp4', 'code' => 'storefront-intro']);

        $response = AppServer::actingAs($user)->tool(ListFilesTool::class, [
            'channel_id' => $channel->id,
            'file_filter' => [
                'code' => 'storefront-intro',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 1 file')
            ->assertStructuredContent(function ($json): void {
                $json->has('files', 1)
                    ->where('files.0.type', 'video')
                    ->etc();
            });
    }

    public function test_supports_pagination(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'asset-one']);
        $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'asset-two']);

        $response = AppServer::actingAs($user)->tool(ListFilesTool::class, [
            'channel_id' => $channel->id,
            'page' => 1,
            'per_page' => 1,
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 1 file')
            ->assertStructuredContent(function ($json): void {
                $json->has('files', 1);
            });
    }

    public function test_supports_order_direction(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $oldestId = $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'oldest']);
        $this->travel(1)->seconds();
        $middleId = $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'middle']);
        $this->travel(1)->seconds();
        $newestId = $this->createFlyCmsFileForUser($user, $channel, ['filename' => 'newest']);

        $response = AppServer::actingAs($user)->tool(ListFilesTool::class, [
            'channel_id' => $channel->id,
            'order_direction' => -1,
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json) use ($newestId, $middleId, $oldestId): void {
                $json->where('files.0.id', $newestId)
                    ->where('files.1.id', $middleId)
                    ->where('files.2.id', $oldestId);
            });
    }

    public function test_does_not_list_files_owned_by_other_users(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ListFilesTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response->assertHasErrors(['No files found.']);
    }

    public function test_returns_error_when_no_files_match(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $this->createFlyCmsFileForUser($user, $channel, ['code' => 'owned-file']);

        $response = AppServer::actingAs($user)->tool(ListFilesTool::class, [
            'channel_id' => $channel->id,
            'file_filter' => [
                'code' => 'nonexistent-file-code',
            ],
        ]);

        $response->assertHasErrors(['No files found.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ListFilesTool::class, [
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
