<?php

namespace Tests\Unit\Mcp\Resources;

use App\Contracts\PlatformManager\FlyCms\Guidelines\WebsiteFlyCmsGuidelines;
use App\Mcp\Resources\OverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\OverviewResource as FlyCmsOverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\WebsiteGuidelinesResource;
use App\Mcp\Support\McpToolName;
use App\Mcp\Support\PlatformManager\FlyCms\FlyCmsGuidelinesRenderer;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\CreateWebsiteTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\ShowWebsiteTool;
use PHPUnit\Framework\TestCase;

class WebsiteGuidelinesResourceTest extends TestCase
{
    public function test_renderer_includes_website_concept_routes_and_meta_fields(): void
    {
        $markdown = FlyCmsGuidelinesRenderer::render(
            WebsiteFlyCmsGuidelines::class,
            WebsiteGuidelinesResource::class,
            [OverviewResource::class, FlyCmsOverviewResource::class],
        );
        $tools = WebsiteFlyCmsGuidelines::relatedTools();

        $this->assertStringContainsString('# FlyCMS Website Guidelines', $markdown);
        $this->assertStringContainsString('**You are here:**', $markdown);
        $this->assertStringContainsString('## What FlyCMS websites are', $markdown);
        $this->assertStringContainsString('## Route patterns', $markdown);
        $this->assertStringContainsString('## Managing meta with meta tools', $markdown);
        $this->assertStringContainsString('## Domains', $markdown);
        $this->assertStringContainsString('## Meta fields', $markdown);
        $this->assertStringContainsString('`tag-seo-title`', $markdown);
        $this->assertStringContainsString('`website_tag_route`', $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['create']), $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['list_themes']), $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['list_meta']), $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['list_domains']), $markdown);
    }

    public function test_resource_returns_rendered_guidelines(): void
    {
        $resource = new WebsiteGuidelinesResource;
        $response = $resource->handle(new \Laravel\Mcp\Request);

        $this->assertStringContainsString('platform-manager://flycms/website-guidelines', $response->content());
        $this->assertStringContainsString((new CreateWebsiteTool)->name(), $response->content());
        $this->assertStringContainsString((new ShowWebsiteTool)->name(), $response->content());
    }
}
