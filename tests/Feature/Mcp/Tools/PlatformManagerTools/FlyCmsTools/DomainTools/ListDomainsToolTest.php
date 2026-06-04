<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\DomainTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\DomainTools\ListDomainsTool;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListDomainsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_domains_for_channel_website(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ListDomainsTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 2 domains')
            ->assertName('platform-manager--flycms--list-domains-tool')
            ->assertDescription('List FlyCMS domains for the website linked to the given channel.')
            ->assertStructuredContent(function ($json): void {
                $json->has('domains', 2)
                    ->where('domains.0.website_id', '01J00000000000000000000001')
                    ->where('domains.0.domain', 'blog.example.com')
                    ->where('domains.0.is_primary', true)
                    ->where('domains.1.website_id', '01J00000000000000000000001')
                    ->where('domains.1.domain', 'www.blog.example.com')
                    ->where('domains.1.is_alias', true);
            });
    }

    public function test_filters_domains_by_domain_name(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ListDomainsTool::class, [
            'channel_id' => $channel->id,
            'domain_filter' => [
                'domain' => 'blog.example.com',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 1 domain')
            ->assertStructuredContent(function ($json): void {
                $json->has('domains', 1)
                    ->where('domains.0.domain', 'blog.example.com')
                    ->etc();
            });
    }

    public function test_uses_channel_website_even_when_filter_includes_different_website_id(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ListDomainsTool::class, [
            'channel_id' => $channel->id,
            'domain_filter' => [
                'website_id' => '01J00000000000000000000002',
            ],
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 2 domains')
            ->assertStructuredContent(function ($json): void {
                $json->has('domains', 2)
                    ->where('domains.0.website_id', '01J00000000000000000000001')
                    ->where('domains.1.website_id', '01J00000000000000000000001');
            });
    }

    public function test_supports_pagination(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ListDomainsTool::class, [
            'channel_id' => $channel->id,
            'page' => 1,
            'per_page' => 1,
        ]);

        $response
            ->assertOk()
            ->assertSee('Found 1 domain')
            ->assertStructuredContent(function ($json): void {
                $json->has('domains', 1);
            });
    }

    public function test_returns_error_when_no_domains_match(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog', '01J00000000000000000000001');

        $response = AppServer::actingAs($user)->tool(ListDomainsTool::class, [
            'channel_id' => $channel->id,
            'domain_filter' => [
                'domain' => 'nonexistent.example.com',
            ],
        ]);

        $response->assertHasErrors(['No domains found.']);
    }

    public function test_returns_error_when_channel_has_no_website_reference(): void
    {
        $user = User::factory()->create();
        $channel = $this->createFlyCmsChannel($user, 'Main Blog');

        $response = AppServer::actingAs($user)->tool(ListDomainsTool::class, [
            'channel_id' => $channel->id,
        ]);

        $response->assertHasErrors(['Channel '.$channel->id.' does not have a FlyCMS website reference.']);
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(ListDomainsTool::class, [
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
