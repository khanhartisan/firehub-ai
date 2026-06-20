<?php

namespace Tests\Unit\Mcp\Resources;

use App\Contracts\PlatformManager\FlyCms\Guidelines\TagFlyCmsGuidelines;
use App\Mcp\Resources\OverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\FlyCmsOverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\TagGuidelinesResource;
use App\Mcp\Support\PlatformManager\FlyCms\FlyCmsGuidelinesRenderer;
use App\Mcp\Support\McpToolName;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\CreateTagTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\ShowTagTool;
use PHPUnit\Framework\TestCase;

class TagGuidelinesResourceTest extends TestCase
{
    public function test_renderer_uses_dynamically_resolved_tool_names(): void
    {
        $markdown = FlyCmsGuidelinesRenderer::render(
            TagFlyCmsGuidelines::class,
            TagGuidelinesResource::class,
            [OverviewResource::class, FlyCmsOverviewResource::class],
        );
        $tools = TagFlyCmsGuidelines::relatedTools();

        $this->assertStringContainsString('**You are here:**', $markdown);
        $this->assertStringContainsString('app://overview', $markdown);
        $this->assertStringContainsString('platform-manager://flycms/overview', $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['create']), $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['update']), $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['show']), $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['publish_article']), $markdown);
        $this->assertStringContainsString('## Liquid template fields', $markdown);
    }

    public function test_resource_returns_rendered_guidelines(): void
    {
        $resource = new TagGuidelinesResource;
        $response = $resource->handle(new \Laravel\Mcp\Request);

        $this->assertStringContainsString('**You are here:**', $response->content());
        $this->assertStringContainsString((new CreateTagTool)->name(), $response->content());
        $this->assertStringContainsString((new ShowTagTool)->name(), $response->content());
    }
}
