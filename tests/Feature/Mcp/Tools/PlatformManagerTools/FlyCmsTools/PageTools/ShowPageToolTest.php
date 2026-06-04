<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\ShowPageTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowPageToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_page_for_channel_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ShowPageTool::class, [
            'channel_id' => $channel->id,
            'page_id' => '01J00000000000000000000041',
        ]);

        $response
            ->assertOk()
            ->assertSee('Page details')
            ->assertName('platform-manager--flycms--show-page-tool')
            ->assertDescription('Show a FlyCMS page by ID for the website linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->where('id', '01J00000000000000000000041')
                    ->where('website_id', '01J00000000000000000000001')
                    ->where('slug', 'about')
                    ->where('title', 'About Us')
                    ->where('seo_title', 'About Us | Sample Blog')
                    ->where('seo_description', 'Learn more about Sample Blog.')
                    ->where('content', '<p>Welcome to our about page.</p>')
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });
    }

    public function test_validation_fails_when_required_fields_are_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ShowPageTool::class, []);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_channel_has_no_website_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ShowPageTool::class, [
            'channel_id' => $channel->id,
            'page_id' => '01J00000000000000000000041',
        ]);

        $response->assertHasErrors(['Channel '.$channel->id.' does not have a FlyCMS website reference.']);
    }

    public function test_returns_error_when_page_does_not_exist(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ShowPageTool::class, [
            'channel_id' => $channel->id,
            'page_id' => 'unknown-page-id',
        ]);

        $response->assertHasErrors(['Page [unknown-page-id] not found.']);
    }

    public function test_returns_error_when_page_belongs_to_another_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ShowPageTool::class, [
            'channel_id' => $channel->id,
            'page_id' => '01J00000000000000000000043',
        ]);

        $response->assertHasErrors(['Page [01J00000000000000000000043] not found.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ShowPageTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'page_id' => '01J00000000000000000000041',
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
