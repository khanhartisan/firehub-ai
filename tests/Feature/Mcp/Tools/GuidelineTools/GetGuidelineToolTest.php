<?php

namespace Tests\Feature\Mcp\Tools\GuidelineTools;

use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\GuidelineTools\GetGuidelineTool;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GetGuidelineToolTest extends TestCase
{
    #[Test]
    public function it_returns_guideline_content_from_uri_identifier(): void
    {
        $response = AppServer::tool(GetGuidelineTool::class, [
            'identifier' => 'platform-manager://flycms/website-guidelines',
        ]);

        $response
            ->assertOk()
            ->assertName('get-guideline-tool')
            ->assertDescription('Get the full markdown content for a guideline/overview document by URI, title, name, or resource class.')
            ->assertStructuredContent(function ($json): void {
                $json->where('resource.uri', 'platform-manager://flycms/website-guidelines')
                    ->where('resource.type', 'guideline')
                    ->where('resource.title', 'FlyCMS Website Guidelines')
                    ->where('resource.description', fn (mixed $description): bool => is_string($description) && str_contains($description, 'FlyCMS website provisioning'))
                    ->where('content', fn (mixed $content): bool => is_string($content) && str_contains($content, '# FlyCMS Website Guidelines'))
                    ->etc();
            });
    }

    #[Test]
    public function it_returns_error_for_unknown_identifier(): void
    {
        $response = AppServer::tool(GetGuidelineTool::class, [
            'identifier' => 'unknown-guideline-resource',
        ]);

        $response->assertHasErrors([
            'Guideline resource not found for identifier [unknown-guideline-resource]. Use `list-guidelines` to discover available resources.',
        ]);
    }
}
