<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools;

use App\Enums\PlatformType;
use App\Facades\Platforms\FlyCms;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\DeleteFileTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteFileToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_file_for_channel_platform(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(DeleteFileTool::class, [
            'channel_id' => $channel->id,
            'file_id' => '01J00000000000000000000071',
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully deleted file [01J00000000000000000000071]')
            ->assertName('platform-manager--flycms--delete-file-tool')
            ->assertDescription('Delete a FlyCMS file on the platform linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->where('file_id', '01J00000000000000000000071');
            });

        $this->assertNull(FlyCms::showFile('01J00000000000000000000071'));
        $this->assertCount(2, FlyCms::listFiles());
    }

    public function test_returns_error_when_file_does_not_exist(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(DeleteFileTool::class, [
            'channel_id' => $channel->id,
            'file_id' => 'unknown-file-id',
        ]);

        $response->assertHasErrors(['File [unknown-file-id] not found.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(DeleteFileTool::class, [
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
