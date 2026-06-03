<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools;

use App\Enums\PlatformType;
use App\Facades\Platforms\FlyCms;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\DeleteTagTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteTagToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_tag_for_channel_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(DeleteTagTool::class, [
            'channel_id' => $channel->id,
            'tag_id' => '01J00000000000000000000021',
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully deleted tag [01J00000000000000000000021]')
            ->assertName('platform-manager--flycms--delete-tag-tool')
            ->assertDescription('Delete a FlyCMS tag on the website linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->where('tag_id', '01J00000000000000000000021');
            });

        $this->assertNull(FlyCms::showTag('01J00000000000000000000021'));
        $this->assertCount(1, FlyCms::listTags('01J00000000000000000000001'));
    }

    public function test_returns_error_when_tag_does_not_exist(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(DeleteTagTool::class, [
            'channel_id' => $channel->id,
            'tag_id' => 'unknown-tag-id',
        ]);

        $response->assertHasErrors(['Tag [unknown-tag-id] not found.']);
    }

    public function test_returns_error_when_tag_belongs_to_another_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(DeleteTagTool::class, [
            'channel_id' => $channel->id,
            'tag_id' => '01J00000000000000000000023',
        ]);

        $response->assertHasErrors(['Tag [01J00000000000000000000023] not found.']);
    }

    public function test_returns_error_when_channel_has_no_website_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(DeleteTagTool::class, [
            'channel_id' => $channel->id,
            'tag_id' => '01J00000000000000000000021',
        ]);

        $response->assertHasErrors(['Channel '.$channel->id.' does not have a FlyCMS website reference.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(DeleteTagTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'tag_id' => '01J00000000000000000000021',
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
