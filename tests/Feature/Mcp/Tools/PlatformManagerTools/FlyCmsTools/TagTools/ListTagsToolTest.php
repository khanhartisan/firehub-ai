<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\ListTagsTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListTagsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_tags_for_channel_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ListTagsTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 2 tags')
            ->assertName('platform-manager--flycms--list-tags-tool')
            ->assertDescription('List FlyCMS tags for the website linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->has('tags', 2)
                    ->has('tags.0.id')
                    ->has('tags.0.name')
                    ->has('tags.1.id')
                    ->has('tags.1.name');
            });
    }

    public function test_filters_tags_by_name(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ListTagsTool::class, [
            'channel_id' => $channel->id,
            'tag_filter' => [
                'name' => 'life',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 1 tag')
            ->assertStructuredContent(function ($json): void {
                $json->has('tags', 1)
                    ->where('tags.0.name', 'Lifestyle')
                    ->etc();
            });
    }

    public function test_supports_pagination(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ListTagsTool::class, [
            'channel_id' => $channel->id,
            'page' => 1,
            'limit' => 1,
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 1 tag')
            ->assertStructuredContent(function ($json): void {
                $json->has('tags', 1);
            });
    }

    public function test_returns_error_when_no_tags_match(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ListTagsTool::class, [
            'channel_id' => $channel->id,
            'tag_filter' => [
                'name' => 'nonexistent-tag-name',
            ],
        ]);

        $response->assertHasErrors(['No tags found.']);
    }

    public function test_returns_error_when_channel_has_no_website_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ListTagsTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response->assertHasErrors(['Channel '.$channel->id.' does not have a FlyCMS website reference.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ListTagsTool::class, [
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
