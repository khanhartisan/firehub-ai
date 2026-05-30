<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AuthorTools\CreateAuthorTool;
use App\Mcp\Tools\AuthorTools\ListAuthorsTool;
use App\Mcp\Tools\AuthorTools\ShowAuthorTool;
use App\Mcp\Tools\AuthorTools\UpdateAuthorContextTool;
use App\Mcp\Tools\AuthorTools\UpdateAuthorTool;
use App\Mcp\Tools\ClientTools\CreateClientTool;
use App\Mcp\Tools\PlatformTools\CreatePlatformTool;
use App\Mcp\Tools\ClientTools\ListClientsTool;
use App\Mcp\Tools\ClientTools\ShowClientTool;
use App\Mcp\Tools\ClientTools\UpdateClientContextTool;
use App\Mcp\Tools\ClientTools\UpdateClientTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('App Server')]
#[Version('0.0.1')]
#[Instructions('MCP Server')]
class AppServer extends Server
{
    protected array $tools = [

        // Author tools
        CreateAuthorTool::class,
        ListAuthorsTool::class,
        ShowAuthorTool::class,
        UpdateAuthorContextTool::class,
        UpdateAuthorTool::class,

        // Client tools
        CreateClientTool::class,
        ListClientsTool::class,
        ShowClientTool::class,
        UpdateClientTool::class,
        UpdateClientContextTool::class,

        // Platform tools
        CreatePlatformTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
