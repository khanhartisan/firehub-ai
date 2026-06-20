<?php

namespace Tests\Unit\Mcp\Support\Guidelines;

use App\Mcp\Resources\OverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\FlyCmsOverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\TagGuidelinesResource;
use App\Mcp\Support\Guidelines\GuidelinesBreadcrumb;
use App\Mcp\Support\Guidelines\McpResourceReference;
use PHPUnit\Framework\TestCase;

class GuidelinesBreadcrumbTest extends TestCase
{
    public function test_resolves_resource_title_and_uri(): void
    {
        $reference = McpResourceReference::fromResourceClass(OverviewResource::class);

        $this->assertSame((new OverviewResource)->title(), $reference['title']);
        $this->assertSame((new OverviewResource)->uri(), $reference['uri']);
    }

    public function test_renders_breadcrumb_from_resource_classes(): void
    {
        $markdown = GuidelinesBreadcrumb::render(
            [OverviewResource::class, FlyCmsOverviewResource::class],
            TagGuidelinesResource::class,
        );

        $this->assertStringContainsString('**You are here:**', $markdown);
        $this->assertStringContainsString('['.(new OverviewResource)->title().']('.(new OverviewResource)->uri().')', $markdown);
        $this->assertStringContainsString('['.(new FlyCmsOverviewResource)->title().']('.(new FlyCmsOverviewResource)->uri().')', $markdown);
        $this->assertStringContainsString('**'.(new TagGuidelinesResource)->title().'**', $markdown);
        $this->assertStringContainsString('`'.(new TagGuidelinesResource)->uri().'`', $markdown);
    }
}
