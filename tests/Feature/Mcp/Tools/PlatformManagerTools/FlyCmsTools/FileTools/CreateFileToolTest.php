<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools;

use App\Enums\PlatformType;
use App\Facades\Platforms\FlyCms;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\CreateFileTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateFileToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_file_for_channel_platform(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(CreateFileTool::class, [
            'channel_id' => $channel->id,
            'file_data' => base64_encode('binary-content'),
            'create_file_data' => [
                'ext' => 'png',
                'filename' => 'new-asset',
                'code' => 'new-asset-code',
                'information' => ['width' => 800],
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created a new file')
            ->assertName('platform-manager--flycms--create-file-tool')
            ->assertDescription('Upload a file to FlyCMS for the platform linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->where('code', 'new-asset-code')
                    ->where('key', 'uploads/new-asset.png')
                    ->where('type', 'image')
                    ->where('mime', 'image/png')
                    ->where('size', 14)
                    ->where('is_uploaded', true)
                    ->where('information', fn (mixed $information): bool => ((array) json_decode(json_encode($information), true)) === ['width' => 800])
                    ->has('user_id')
                    ->has('id')
                    ->has('url')
                    ->etc();
            });

        $this->assertCount(4, FlyCms::listFiles());
    }

    public function test_validation_fails_when_required_fields_are_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(CreateFileTool::class, []);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_create_file_data_is_empty(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(CreateFileTool::class, [
            'channel_id' => $channel->id,
            'file_data' => base64_encode('binary-content'),
            'create_file_data' => [],
        ]);

        $response->assertHasErrors(['Provide create_file_data with at least ext.']);
    }

    public function test_returns_error_when_file_data_is_not_valid_base64(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(CreateFileTool::class, [
            'channel_id' => $channel->id,
            'file_data' => 'not-valid-base64!!!',
            'create_file_data' => [
                'ext' => 'png',
            ],
        ]);

        $response->assertHasErrors(['file_data must be valid base64-encoded content.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(CreateFileTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'file_data' => base64_encode('binary-content'),
            'create_file_data' => [
                'ext' => 'png',
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
