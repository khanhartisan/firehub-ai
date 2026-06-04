<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools;

use App\Enums\PlatformType;
use App\Facades\Platforms\FlyCms;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\CreatePageTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePageToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_page_for_channel_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(CreatePageTool::class, [
            'channel_id' => $channel->id,
            'create_page_data' => [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'seo_title' => 'Privacy | Sample Blog',
                'seo_description' => 'Our privacy policy.',
                'content' => '<p>Privacy details.</p>',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created a new page')
            ->assertName('platform-manager--flycms--create-page-tool')
            ->assertDescription('Create a FlyCMS page on the website linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->where('slug', 'privacy')
                    ->where('title', 'Privacy Policy')
                    ->where('website_id', '01J00000000000000000000001')
                    ->where('seo_title', 'Privacy | Sample Blog')
                    ->where('seo_description', 'Our privacy policy.')
                    ->where('content', '<p>Privacy details.</p>')
                    ->has('id')
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });

        $this->assertCount(3, FlyCms::listPages('01J00000000000000000000001'));
    }

    public function test_uses_channel_website_even_when_payload_includes_different_website_id(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(CreatePageTool::class, [
            'channel_id' => $channel->id,
            'create_page_data' => [
                'website_id' => '01J00000000000000000000002',
                'slug' => 'terms',
                'title' => 'Terms',
            ],
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json): void {
                $json->where('website_id', '01J00000000000000000000001')
                    ->where('title', 'Terms')
                    ->etc();
            });
    }

    public function test_validation_fails_when_required_fields_are_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(CreatePageTool::class, []);

        $response->assertHasErrors();
    }

    public function test_returns_error_when_create_page_data_is_empty(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(CreatePageTool::class, [
            'channel_id' => $channel->id,
            'create_page_data' => [],
        ]);

        $response->assertHasErrors(['Provide create_page_data with at least slug and title.']);
    }

    public function test_returns_error_when_channel_has_no_website_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(CreatePageTool::class, [
            'channel_id' => $channel->id,
            'create_page_data' => [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
            ],
        ]);

        $response->assertHasErrors(['Channel '.$channel->id.' does not have a FlyCMS website reference.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(CreatePageTool::class, [
            'channel_id' => '01J0000000000000000000000',
            'create_page_data' => [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
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
