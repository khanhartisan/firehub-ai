<?php

namespace Tests\Feature\Mcp\Tools\PlatformTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\PlatformTools\CreatePlatformTool;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePlatformToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_platform_with_required_fields(): void
    {
        $name = 'Production FlyCMS';

        $response = AppServer::actingAs($this->superUser())->tool(CreatePlatformTool::class, [
            'name' => $name,
            'type' => PlatformType::FLYCMS->value,
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully created a new platform')
            ->assertName('create-platform-tool')
            ->assertDescription('Create a new platform.')
            ->assertStructuredContent(function ($json) use ($name): void {
                $json->where('name', $name)
                    ->where('type', PlatformType::FLYCMS->value)
                    ->where('channels_count', 0)
                    ->has('created_at')
                    ->has('updated_at')
                    ->etc();
            });

        $this->assertDatabaseHas('platforms', [
            'name' => $name,
            'type' => PlatformType::FLYCMS->value,
            'channels_count' => 0,
        ]);
    }

    public function test_validation_fails_when_name_is_missing(): void
    {
        $response = AppServer::actingAs($this->superUser())->tool(CreatePlatformTool::class, [
            'type' => PlatformType::FLYCMS->value,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('platforms', 0);
    }

    public function test_validation_fails_when_type_is_missing(): void
    {
        $response = AppServer::actingAs($this->superUser())->tool(CreatePlatformTool::class, [
            'name' => 'Production FlyCMS',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('platforms', 0);
    }

    public function test_validation_fails_when_name_is_blank(): void
    {
        $response = AppServer::actingAs($this->superUser())->tool(CreatePlatformTool::class, [
            'name' => '   ',
            'type' => PlatformType::FLYCMS->value,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('platforms', 0);
    }

    public function test_validation_fails_when_name_is_not_unique(): void
    {
        $platform = new Platform;
        $platform->name = 'Production FlyCMS';
        $platform->type = PlatformType::FLYCMS;
        $platform->save();

        $response = AppServer::actingAs($this->superUser())->tool(CreatePlatformTool::class, [
            'name' => 'Production FlyCMS',
            'type' => PlatformType::FLYCMS->value,
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('platforms', 1);
    }

    public function test_validation_fails_when_type_is_invalid(): void
    {
        $response = AppServer::actingAs($this->superUser())->tool(CreatePlatformTool::class, [
            'name' => 'Production FlyCMS',
            'type' => 'wordpress',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseCount('platforms', 0);
    }

    public function test_tool_is_not_available_for_non_super_user(): void
    {
        $response = AppServer::actingAs($this->regularUser())->tool(CreatePlatformTool::class, [
            'name' => 'Production FlyCMS',
            'type' => PlatformType::FLYCMS->value,
        ]);

        $response->assertHasErrors(['Tool [create-platform-tool] not found.']);

        $this->assertDatabaseCount('platforms', 0);
    }

    private function superUser(): User
    {
        $user = User::factory()->create();
        $user->is_super = true;
        $user->save();

        return $user;
    }

    private function regularUser(): User
    {
        $user = User::factory()->create();
        $user->is_super = false;
        $user->save();

        return $user;
    }
}
