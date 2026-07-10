<?php

namespace Tests\Feature\Mcp\Tools\GuidelineTools;

use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\GuidelineTools\ListGuidelinesTool;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListGuidelinesToolTest extends TestCase
{
    #[Test]
    public function it_lists_all_guideline_resources(): void
    {
        $response = AppServer::tool(ListGuidelinesTool::class);

        $response
            ->assertOk()
            ->assertSee('Available guideline resources:')
            ->assertName('list-guidelines-tool')
            ->assertDescription('List all available overview/guideline documents and their resource URIs so clients can fetch docs via tools even when resources/read is unavailable.')
            ->assertStructuredContent(function ($json): void {
                $json->has('guideline_resources', 9)
                    ->where('guideline_resources.0.uri', 'app://content-core/overview')
                    ->where('guideline_resources.0.description', fn (mixed $description): bool => is_string($description) && $description !== '')
                    ->etc();
            });
    }

    #[Test]
    public function it_filters_by_type_and_search(): void
    {
        $response = AppServer::tool(ListGuidelinesTool::class, [
            'type' => 'guideline',
            'search' => 'website',
        ]);

        $response
            ->assertOk()
            ->assertStructuredContent(function ($json): void {
                $json->has('guideline_resources', 1)
                    ->where('guideline_resources.0.uri', 'platform-manager://flycms/website-guidelines')
                    ->where('guideline_resources.0.description', fn (mixed $description): bool => is_string($description) && str_contains($description, 'FlyCMS website'))
                    ->where('guideline_resources.0.type', 'guideline')
                    ->etc();
            });
    }
}
