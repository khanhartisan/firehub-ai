<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools;

use App\Enums\PlatformType;
use App\Facades\Platforms\FlyCms;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\UpdateTagTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTagToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_tag_for_channel_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(UpdateTagTool::class, [
            'channel_id' => $channel->id,
            'tag_id' => '01J00000000000000000000021',
            'update_tag_data' => [
                'name' => 'Tech',
                'slug' => 'tech',
                'is_featured' => false,
                'thumbnail_file_id' => '01J00000000000000000000072',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the tag')
            ->assertName('platform-manager--flycms--update-tag-tool')
            ->assertDescription('Update a FlyCMS tag on the website linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->where('id', '01J00000000000000000000021')
                    ->where('name', 'Tech')
                    ->where('slug', 'tech')
                    ->where('is_featured', false)
                    ->where('website_id', '01J00000000000000000000001')
                    ->where('thumbnail_file_id', '01J00000000000000000000072')
                    ->where('thumbnailFile', fn (mixed $thumbnail): bool => ((array) json_decode(json_encode($thumbnail), true))['key'] === 'uploads/weekend-ideas.webp')
                    ->etc();
            });

        $tag = FlyCms::showTag('01J00000000000000000000021');
        $this->assertNotNull($tag);
        $this->assertSame('Tech', $tag->getData()['name']);
        $this->assertSame('01J00000000000000000000072', $tag->getData()['thumbnail_file_id']);
    }

    public function test_validation_fails_when_update_tag_data_is_missing(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(UpdateTagTool::class, [
            'channel_id' => $channel->id,
            'tag_id' => '01J00000000000000000000021',
        ]);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_update_tag_data_is_empty(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(UpdateTagTool::class, [
            'channel_id' => $channel->id,
            'tag_id' => '01J00000000000000000000021',
            'update_tag_data' => [],
        ]);

        $response->assertHasErrors(['Provide at least one field in update_tag_data.']);
    }

    public function test_returns_error_when_tag_does_not_exist(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(UpdateTagTool::class, [
            'channel_id' => $channel->id,
            'tag_id' => 'unknown-tag-id',
            'update_tag_data' => [
                'name' => 'Missing',
            ],
        ]);

        $response->assertHasErrors(['Tag [unknown-tag-id] not found.']);
    }

    public function test_returns_error_when_tag_belongs_to_another_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(UpdateTagTool::class, [
            'channel_id' => $channel->id,
            'tag_id' => '01J00000000000000000000023',
            'update_tag_data' => [
                'name' => 'Shop',
            ],
        ]);

        $response->assertHasErrors(['Tag [01J00000000000000000000023] not found.']);
    }

    public function test_returns_error_when_channel_has_no_website_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(UpdateTagTool::class, [
            'channel_id' => $channel->id,
            'tag_id' => '01J00000000000000000000021',
            'update_tag_data' => [
                'name' => 'Tech',
            ],
        ]);

        $response->assertHasErrors(['Channel '.$channel->id.' does not have a FlyCMS website reference.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(UpdateTagTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'tag_id' => '01J00000000000000000000021',
            'update_tag_data' => [
                'name' => 'Tech',
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
