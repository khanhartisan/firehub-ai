<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools;

use App\Enums\PlatformType;
use App\Facades\Platforms\FlyCms;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\UpdatePageTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatePageToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_page_for_channel_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(UpdatePageTool::class, [
            'channel_id' => $channel->id,
            'page_id' => '01J00000000000000000000041',
            'update_page_data' => [
                'slug' => 'about-us',
                'title' => 'About Our Team',
                'seo_title' => 'About | Sample Blog',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the page')
            ->assertName('platform-manager--flycms--update-page-tool')
            ->assertDescription('Update a FlyCMS page on the website linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->where('id', '01J00000000000000000000041')
                    ->where('slug', 'about-us')
                    ->where('title', 'About Our Team')
                    ->where('seo_title', 'About | Sample Blog')
                    ->where('website_id', '01J00000000000000000000001')
                    ->etc();
            });

        $page = FlyCms::showPage('01J00000000000000000000041');
        $this->assertNotNull($page);
        $this->assertSame('About Our Team', $page->getData()['title']);
        $this->assertSame('about-us', $page->getData()['slug']);
    }

    public function test_validation_fails_when_update_page_data_is_missing(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(UpdatePageTool::class, [
            'channel_id' => $channel->id,
            'page_id' => '01J00000000000000000000041',
        ]);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_update_page_data_is_empty(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(UpdatePageTool::class, [
            'channel_id' => $channel->id,
            'page_id' => '01J00000000000000000000041',
            'update_page_data' => [],
        ]);

        $response->assertHasErrors(['Provide at least one field in update_page_data.']);
    }

    public function test_returns_error_when_page_does_not_exist(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(UpdatePageTool::class, [
            'channel_id' => $channel->id,
            'page_id' => 'unknown-page-id',
            'update_page_data' => [
                'title' => 'Updated',
            ],
        ]);

        $response->assertHasErrors(['Page [unknown-page-id] not found.']);
    }

    public function test_returns_error_when_page_belongs_to_another_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(UpdatePageTool::class, [
            'channel_id' => $channel->id,
            'page_id' => '01J00000000000000000000043',
            'update_page_data' => [
                'title' => 'Updated',
            ],
        ]);

        $response->assertHasErrors(['Page [01J00000000000000000000043] not found.']);
    }

    public function test_returns_error_when_channel_has_no_website_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(UpdatePageTool::class, [
            'channel_id' => $channel->id,
            'page_id' => '01J00000000000000000000041',
            'update_page_data' => [
                'title' => 'Updated',
            ],
        ]);

        $response->assertHasErrors(['Channel '.$channel->id.' does not have a FlyCMS website reference.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(UpdatePageTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'page_id' => '01J00000000000000000000041',
            'update_page_data' => [
                'title' => 'Updated',
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
