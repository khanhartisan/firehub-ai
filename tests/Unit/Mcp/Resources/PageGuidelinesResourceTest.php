<?php

namespace Tests\Unit\Mcp\Resources;

use App\Contracts\PlatformManager\FlyCms\Guidelines\PageFlyCmsGuidelines;
use App\Mcp\Resources\OverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\OverviewResource as FlyCmsOverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\PageGuidelinesResource;
use App\Mcp\Support\McpToolName;
use App\Mcp\Support\PlatformManager\FlyCms\FlyCmsGuidelinesRenderer;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\CreatePageTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\ShowPageTool;
use PHPUnit\Framework\TestCase;

class PageGuidelinesResourceTest extends TestCase
{
    public function test_renderer_includes_page_concept_and_liquid_guidance(): void
    {
        $markdown = FlyCmsGuidelinesRenderer::render(
            PageFlyCmsGuidelines::class,
            PageGuidelinesResource::class,
            [OverviewResource::class, FlyCmsOverviewResource::class],
        );
        $tools = PageFlyCmsGuidelines::relatedTools();

        $this->assertStringContainsString('# FlyCMS Page Guidelines', $markdown);
        $this->assertStringContainsString('**You are here:**', $markdown);
        $this->assertStringContainsString('## What FlyCMS pages are', $markdown);
        $this->assertStringContainsString('## Liquid template fields', $markdown);
        $this->assertStringContainsString('parsed by the Liquid engine', $markdown);
        $this->assertStringContainsString('## `content` field (Liquid)', $markdown);
        $this->assertStringContainsString('`page-seo-title`', $markdown);
        $this->assertStringContainsString('{{ page.title }}', $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['create']), $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['list']), $markdown);
    }

    public function test_resource_returns_rendered_guidelines(): void
    {
        $resource = new PageGuidelinesResource;
        $response = $resource->handle(new \Laravel\Mcp\Request);

        $this->assertStringContainsString('platform-manager://flycms/page-guidelines', $response->content());
        $this->assertStringContainsString((new CreatePageTool)->name(), $response->content());
        $this->assertStringContainsString((new ShowPageTool)->name(), $response->content());
    }
}
