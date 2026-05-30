<?php

namespace Tests\Feature\Mcp\Tools\PlatformTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\PlatformTools\ListPlatformsTool;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListPlatformsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_platforms_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $this->createPlatform('Staging FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($user)->tool(ListPlatformsTool::class);

        $response
            ->assertOk()
            ->assertSee('Found 2 platforms')
            ->assertName('list-platforms-tool')
            ->assertDescription('Show the list of available platforms.')
            ->assertStructuredContent(function ($json): void {
                $json->has('platforms', 2)
                    ->where('platforms', fn (mixed $platforms): bool => collect($platforms)
                        ->pluck('name')
                        ->sort()
                        ->values()
                        ->all() === ['Production FlyCMS', 'Staging FlyCMS'])
                    ->etc();
            });
    }

    public function test_uses_singular_platform_label_for_single_result(): void
    {
        $user = User::factory()->create();
        $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($user)->tool(ListPlatformsTool::class);

        $response
            ->assertOk()
            ->assertSee('Found 1 platform')
            ->assertStructuredContent(function ($json): void {
                $json->has('platforms', 1)
                    ->where('platforms.0.name', 'Production FlyCMS')
                    ->where('platforms.0.type', PlatformType::FLYCMS->value)
                    ->etc();
            });
    }

    public function test_returns_error_when_no_platforms_exist(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(ListPlatformsTool::class);

        $response->assertHasErrors(['No platforms found.']);
    }

    public function test_is_available_for_non_super_user(): void
    {
        $user = User::factory()->create();
        $user->is_super = false;
        $user->save();

        $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($user)->tool(ListPlatformsTool::class);

        $response
            ->assertOk()
            ->assertSee('Found 1 platform')
            ->assertStructuredContent(function ($json): void {
                $json->has('platforms', 1)
                    ->where('platforms.0.name', 'Production FlyCMS')
                    ->etc();
            });
    }

    public function test_lists_same_platforms_for_all_users(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $this->createPlatform('Staging FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($user)->tool(ListPlatformsTool::class);
        $otherResponse = AppServer::actingAs($otherUser)->tool(ListPlatformsTool::class);

        $response->assertOk()->assertSee('Found 2 platforms');
        $otherResponse->assertOk()->assertSee('Found 2 platforms');
    }

    private function createPlatform(string $name, PlatformType $type): Platform
    {
        $platform = new Platform;
        $platform->name = $name;
        $platform->type = $type;
        $platform->save();

        return $platform;
    }
}
