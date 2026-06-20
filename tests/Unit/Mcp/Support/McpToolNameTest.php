<?php

namespace Tests\Unit\Mcp\Support;

use App\Mcp\Support\McpToolName;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\CreateTagTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\UpdateTagTool;
use PHPUnit\Framework\TestCase;

class McpToolNameTest extends TestCase
{
    public function test_resolves_tool_name_from_class(): void
    {
        $this->assertSame(
            (new CreateTagTool)->name(),
            McpToolName::resolve(CreateTagTool::class),
        );
    }

    public function test_quotes_resolved_tool_name(): void
    {
        $this->assertSame(
            '`'.(new UpdateTagTool)->name().'`',
            McpToolName::quoted(UpdateTagTool::class),
        );
    }

    public function test_quotes_list_joins_multiple_tools(): void
    {
        $this->assertSame(
            '`'.(new CreateTagTool)->name().'` or `'.(new UpdateTagTool)->name().'`',
            McpToolName::quotedList([CreateTagTool::class, UpdateTagTool::class]),
        );
    }
}
