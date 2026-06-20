<?php

namespace Tests\Unit\Mcp\Resources;

use App\Contracts\PlatformManager\FlyCms\Guidelines\MenuFlyCmsGuidelines;
use App\Mcp\Resources\OverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\FlyCmsOverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\MenuGuidelinesResource;
use App\Mcp\Support\McpToolName;
use App\Mcp\Support\PlatformManager\FlyCms\FlyCmsGuidelinesRenderer;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\CreateMenuTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\ShowMenuTool;
use PHPUnit\Framework\TestCase;

class MenuGuidelinesResourceTest extends TestCase
{
    public function test_renderer_includes_menu_concept_and_link_guidance(): void
    {
        $markdown = FlyCmsGuidelinesRenderer::render(
            MenuFlyCmsGuidelines::class,
            MenuGuidelinesResource::class,
            [OverviewResource::class, FlyCmsOverviewResource::class],
        );
        $tools = MenuFlyCmsGuidelines::relatedTools();

        $this->assertStringContainsString('# FlyCMS Menu Guidelines', $markdown);
        $this->assertStringContainsString('**You are here:**', $markdown);
        $this->assertStringContainsString('## What FlyCMS menus are', $markdown);
        $this->assertStringContainsString('## Menu keys', $markdown);
        $this->assertStringContainsString('## Link formats', $markdown);
        $this->assertStringContainsString('link:website_tag', $markdown);
        $this->assertStringContainsString('/page/about', $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['create']), $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['list']), $markdown);
    }

    public function test_resource_returns_rendered_guidelines(): void
    {
        $resource = new MenuGuidelinesResource;
        $response = $resource->handle(new \Laravel\Mcp\Request);

        $this->assertStringContainsString('file://resources/menu-guidelines-resource', $response->content());
        $this->assertStringContainsString((new CreateMenuTool)->name(), $response->content());
        $this->assertStringContainsString((new ShowMenuTool)->name(), $response->content());
    }
}
