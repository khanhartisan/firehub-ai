<?php

namespace Tests\Unit\Mcp\Resources;

use App\Contracts\PlatformManager\FlyCms\Guidelines\FileFlyCmsGuidelines;
use App\Mcp\Resources\OverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\FileGuidelinesResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\OverviewResource as FlyCmsOverviewResource;
use App\Mcp\Support\McpToolName;
use App\Mcp\Support\PlatformManager\FlyCms\FlyCmsGuidelinesRenderer;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\CreateFileTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\ShowFileTool;
use PHPUnit\Framework\TestCase;

class FileGuidelinesResourceTest extends TestCase
{
    public function test_renderer_includes_file_concept_and_upload_guidance(): void
    {
        $markdown = FlyCmsGuidelinesRenderer::render(
            FileFlyCmsGuidelines::class,
            FileGuidelinesResource::class,
            [OverviewResource::class, FlyCmsOverviewResource::class],
        );
        $tools = FileFlyCmsGuidelines::relatedTools();

        $this->assertStringContainsString('# FlyCMS File Guidelines', $markdown);
        $this->assertStringContainsString('**You are here:**', $markdown);
        $this->assertStringContainsString('## What FlyCMS files are', $markdown);
        $this->assertStringContainsString('## Upload workflow', $markdown);
        $this->assertStringContainsString('Base64-encoded', $markdown);
        $this->assertStringContainsString('thumbnail_file_id', $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['create']), $markdown);
        $this->assertStringContainsString(McpToolName::quoted($tools['list']), $markdown);
    }

    public function test_resource_returns_rendered_guidelines(): void
    {
        $resource = new FileGuidelinesResource;
        $response = $resource->handle(new \Laravel\Mcp\Request);

        $this->assertStringContainsString('platform-manager://flycms/file-guidelines', $response->content());
        $this->assertStringContainsString((new CreateFileTool)->name(), $response->content());
        $this->assertStringContainsString((new ShowFileTool)->name(), $response->content());
    }
}
