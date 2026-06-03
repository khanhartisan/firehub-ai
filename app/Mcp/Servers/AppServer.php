<?php

namespace App\Mcp\Servers;

use App\Mcp\Server\Methods\AppCallTool;
use App\Mcp\Tools\ArticleTools\CreateArticleTool;
use App\Mcp\Tools\ArticleTools\ListArticlesTool;
use App\Mcp\Tools\ArticleTools\ShowArticleTool;
use App\Mcp\Tools\ArticleTools\UpdateArticleContextTool;
use App\Mcp\Tools\AuthorTools\CreateAuthorTool;
use App\Mcp\Tools\AuthorTools\ListAuthorsTool;
use App\Mcp\Tools\AuthorTools\ShowAuthorTool;
use App\Mcp\Tools\AuthorTools\UpdateAuthorContextTool;
use App\Mcp\Tools\AuthorTools\UpdateAuthorTool;
use App\Mcp\Tools\ChannelTools\CreateChannelTool;
use App\Mcp\Tools\ChannelTools\GetChannelConfigSchemaTool;
use App\Mcp\Tools\ChannelTools\ListChannelsTool;
use App\Mcp\Tools\ChannelTools\ShowChannelTool;
use App\Mcp\Tools\ChannelTools\UpdateChannelTool;
use App\Mcp\Tools\ClientTools\CreateClientTool;
use App\Mcp\Tools\ClientTools\ListClientsTool;
use App\Mcp\Tools\ClientTools\ShowClientTool;
use App\Mcp\Tools\ClientTools\UpdateClientContextTool;
use App\Mcp\Tools\ClientTools\UpdateClientTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\ShowTagTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\CreateWebsiteTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\ShowWebsiteTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\UpdateWebsiteTool;
use App\Mcp\Tools\PlatformTools\CreatePlatformTool;
use App\Mcp\Tools\PlatformTools\ListPlatformsTool;
use App\Mcp\Tools\PlatformTools\UpdatePlatformConfigTool;
use App\Mcp\Tools\PlatformTools\UpdatePlatformTool;
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

        // Article tools
        CreateArticleTool::class,
        ListArticlesTool::class,
        ShowArticleTool::class,
        UpdateArticleContextTool::class,

        // Author tools
        CreateAuthorTool::class,
        ListAuthorsTool::class,
        ShowAuthorTool::class,
        UpdateAuthorContextTool::class,
        UpdateAuthorTool::class,

        // Channel tools
        CreateChannelTool::class,
        GetChannelConfigSchemaTool::class,
        ListChannelsTool::class,
        ShowChannelTool::class,
        UpdateChannelTool::class,

        // Client tools
        CreateClientTool::class,
        ListClientsTool::class,
        ShowClientTool::class,
        UpdateClientTool::class,
        UpdateClientContextTool::class,

        // Platform tools
        CreatePlatformTool::class, // super only
        ListPlatformsTool::class,
        UpdatePlatformTool::class, // super only
        UpdatePlatformConfigTool::class, // super only

        // FlyCms tools
        CreateWebsiteTool::class,
        ShowWebsiteTool::class,
        UpdateWebsiteTool::class,
        ShowTagTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];

    protected function boot(): void
    {
        $this->addMethod('tools/call', AppCallTool::class);
    }
}
