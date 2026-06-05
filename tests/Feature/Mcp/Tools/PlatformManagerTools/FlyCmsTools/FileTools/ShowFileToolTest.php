<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\Concerns\CreatesFlyCmsFilesForUser;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\ShowFileTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowFileToolTest extends TestCase
{
    use CreatesFlyCmsFilesForUser;
    use RefreshDatabase;

    public function test_shows_file_for_channel_platform(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');
        $fileId = $this->createFlyCmsFileForUser($user, $channel, [
            'filename' => 'hero-banner',
            'ext' => 'jpg',
            'code' => 'hero-banner',
            'information' => ['alt' => 'Sample blog hero image'],
        ]);

        $response = AppServer::actingAs($user)->tool(ShowFileTool::class, [
            'channel_id' => $channel->id,
            'file_id' => $fileId,
        ]);

        $response
            ->assertOk()
            ->assertSee('File details')
            ->assertName('platform-manager--flycms--show-file-tool')
            ->assertDescription('Show a FlyCMS file by ID for the platform linked to the given channel.')
            ->assertStructuredContent(function ($json) use ($fileId): void {
                $json->where('id', $fileId)
                    ->where('code', 'hero-banner')
                    ->where('key', 'uploads/hero-banner.jpg')
                    ->where('type', 'image')
                    ->where('mime', 'image/jpeg')
                    ->where('is_uploaded', true)
                    ->where('information', fn (mixed $information): bool => ((array) json_decode(json_encode($information), true)) === ['alt' => 'Sample blog hero image'])
                    ->has('url')
                    ->has('size')
                    ->etc();
            });
    }

    public function test_validation_fails_when_required_fields_are_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowFileTool::class, []);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_file_belongs_to_another_user(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ShowFileTool::class, [
            'channel_id' => $channel->id,
            'file_id' => '01J00000000000000000000071',
        ]);

        $response->assertHasErrors(['File [01J00000000000000000000071] not found.']);
    }

    public function test_returns_error_when_file_does_not_exist(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ShowFileTool::class, [
            'channel_id' => $channel->id,
            'file_id' => 'unknown-file-id',
        ]);

        $response->assertHasErrors(['File [unknown-file-id] not found.']);
    }

    public function test_returns_error_when_channel_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $channel = $this->createFlyCmsChannel($otherUser, 'Other Channel');

        $response = AppServer::actingAs($user)->tool(ShowFileTool::class, [
            'channel_id' => $channel->id,
            'file_id' => '01J00000000000000000000071',
        ]);

        $response->assertHasErrors(['Channel not found or you do not have access to this channel.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ShowFileTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'file_id' => '01J00000000000000000000071',
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
