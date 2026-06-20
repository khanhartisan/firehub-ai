<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MetaTools;

use App\Enums\PlatformType;
use App\Facades\Platforms\FlyCms;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MetaTools\PutMetaTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PutMetaToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_upserts_meta_for_channel_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(PutMetaTool::class, [
            'channel_id' => $channel->id,
            'put_meta_data' => [
                'meta' => [
                    ['key' => 'site-name', 'value' => 'Updated Blog'],
                    ['key' => 'home-seo-title', 'value' => 'Updated Home Title'],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully upserted 2 meta entries')
            ->assertName('platform-manager--flycms--put-meta-tool')
            ->assertDescription('Upsert FlyCMS meta entries on the website linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->has('meta', 2)
                    ->where('meta.0.key', 'site-name')
                    ->where('meta.0.value', 'Updated Blog');
            });

        $website = FlyCms::showWebsite('01J00000000000000000000001');

        $this->assertSame('Updated Blog', $website->getData()['meta']['site-name']);
        $this->assertSame('Updated Home Title', $website->getData()['meta']['home-seo-title']);
    }

    public function test_returns_error_when_put_meta_data_is_missing(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(PutMetaTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response->assertHasErrors(['Provide put_meta_data with at least one meta entry.']);
    }

    public function test_returns_error_when_channel_has_no_website_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(PutMetaTool::class, [
            'channel_id' => $channel->id,
            'put_meta_data' => [
                'meta' => [
                    ['key' => 'site-name', 'value' => 'Updated Blog'],
                ],
            ],
        ]);

        $response->assertHasErrors(['Channel '.$channel->id.' does not have a FlyCMS website reference.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(PutMetaTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'put_meta_data' => [
                'meta' => [
                    ['key' => 'site-name', 'value' => 'Updated Blog'],
                ],
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
