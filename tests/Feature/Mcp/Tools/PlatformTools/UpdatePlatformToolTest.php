<?php

namespace Tests\Feature\Mcp\Tools\PlatformTools;

use App\Enums\PlatformType;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\PlatformTools\UpdatePlatformTool;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatePlatformToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_platform_name(): void
    {
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformTool::class, [
            'platform_id' => $platform->id,
            'name' => 'Staging FlyCMS',
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the platform')
            ->assertName('update-platform-tool')
            ->assertDescription('Update an existing platform.')
            ->assertStructuredContent(function ($json): void {
                $json->where('name', 'Staging FlyCMS')->etc();
            });

        $this->assertDatabaseHas('platforms', [
            'id' => $platform->id,
            'name' => 'Staging FlyCMS',
        ]);
    }

    public function test_updates_platform_type(): void
    {
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformTool::class, [
            'platform_id' => $platform->id,
            'type' => PlatformType::FLYCMS->value,
        ]);

        $response->assertOk();

        $platform->refresh();
        $this->assertSame(PlatformType::FLYCMS, $platform->type);
    }

    public function test_updates_name_and_type_together(): void
    {
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformTool::class, [
            'platform_id' => $platform->id,
            'name' => 'Staging FlyCMS',
            'type' => PlatformType::FLYCMS->value,
        ]);

        $response->assertOk();

        $platform->refresh();
        $this->assertSame('Staging FlyCMS', $platform->name);
        $this->assertSame(PlatformType::FLYCMS, $platform->type);
    }

    public function test_validation_fails_when_platform_id_is_missing(): void
    {
        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformTool::class, [
            'name' => 'Staging FlyCMS',
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_no_fields_to_update(): void
    {
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformTool::class, [
            'platform_id' => $platform->id,
        ]);

        $response->assertHasErrors(['Provide at least one field to update (name or type).']);
    }

    public function test_validation_fails_when_name_is_blank(): void
    {
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformTool::class, [
            'platform_id' => $platform->id,
            'name' => '   ',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseHas('platforms', [
            'id' => $platform->id,
            'name' => 'Production FlyCMS',
        ]);
    }

    public function test_validation_fails_when_name_is_not_unique(): void
    {
        $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);
        $platform = $this->createPlatform('Secondary FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformTool::class, [
            'platform_id' => $platform->id,
            'name' => 'Production FlyCMS',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseHas('platforms', [
            'id' => $platform->id,
            'name' => 'Secondary FlyCMS',
        ]);
    }

    public function test_validation_fails_when_type_is_invalid(): void
    {
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformTool::class, [
            'platform_id' => $platform->id,
            'type' => 'wordpress',
        ]);

        $response->assertHasErrors();

        $this->assertDatabaseHas('platforms', [
            'id' => $platform->id,
            'type' => PlatformType::FLYCMS->value,
        ]);
    }

    public function test_returns_error_when_platform_is_not_found(): void
    {
        $response = AppServer::actingAs($this->superUser())->tool(UpdatePlatformTool::class, [
            'platform_id' => '01J0000000000000000000000',
            'name' => 'Staging FlyCMS',
        ]);

        $response->assertHasErrors(['Platform not found.']);
    }

    public function test_tool_is_not_available_for_non_super_user(): void
    {
        $platform = $this->createPlatform('Production FlyCMS', PlatformType::FLYCMS);

        $response = AppServer::actingAs($this->regularUser())->tool(UpdatePlatformTool::class, [
            'platform_id' => $platform->id,
            'name' => 'Staging FlyCMS',
        ]);

        $response->assertHasErrors(['Tool [update-platform-tool] not found.']);

        $this->assertDatabaseHas('platforms', [
            'id' => $platform->id,
            'name' => 'Production FlyCMS',
        ]);
    }

    private function createPlatform(string $name, PlatformType $type): Platform
    {
        $platform = new Platform;
        $platform->name = $name;
        $platform->type = $type;
        $platform->save();

        return $platform;
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
