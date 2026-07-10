<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\ContentCoreOverviewResource;
use App\Mcp\Resources\OverviewResource;
use App\Mcp\Resources\PublishingChannelsOverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\OverviewResource as FlyCmsOverviewResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\FileGuidelinesResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\MenuGuidelinesResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\PageGuidelinesResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\TagGuidelinesResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\WebsiteGuidelinesResource;
use App\Mcp\Server\Methods\AppCallTool;
use App\Mcp\Tools\ArticleTools\CreateArticleTool;
use App\Mcp\Tools\ArticleTools\ListArticlesTool;
use App\Mcp\Tools\ArticleTools\PublishArticleTool;
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
use App\Mcp\Tools\GuidelineTools\GetGuidelineTool;
use App\Mcp\Tools\GuidelineTools\ListGuidelinesTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\DomainTools\ListDomainsTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\DomainTools\ShowDomainTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\CreateFileTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\DeleteFileTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\ListFilesTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\ShowFileTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\UpdateFileTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\CreateMenuTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\DeleteMenuTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\ListMenusTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\ShowMenuTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MenuTools\UpdateMenuTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MetaTools\DeleteMetaTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MetaTools\ListMetaTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MetaTools\PutMetaTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\CreatePageTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\DeletePageTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\ListPagesTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\ShowPageTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\PageTools\UpdatePageTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\CreateTagTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\DeleteTagTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\ListTagsTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\ShowTagTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools\UpdateTagTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\ThemeTools\ListThemesTool;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\ThemeTools\ShowThemeTool;
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
#[Instructions('MCP server for content operations and platform publishing. Read app://overview first; for FlyCMS, also read platform-manager://flycms/overview.')]
class AppServer extends Server
{
    protected array $tools = [];

    protected array $resources = [
        OverviewResource::class,
        ContentCoreOverviewResource::class,
        PublishingChannelsOverviewResource::class,
    ];

    protected array $prompts = [
        //
    ];

    public int $maxPaginationLength = 500;

    public int $defaultPaginationLength = 500;

    protected function boot(): void
    {
        $this->addMethod('tools/call', AppCallTool::class);

        // Register resources
        $this->registerFlyCmsResources();

        // Register tools
        $this->registerArticleTools();
        $this->registerAuthorTools();
        $this->registerChannelTools();
        $this->registerClientTools();
        $this->registerGuidelineTools();
        $this->registerPlatformTools();
        $this->registerPlatformManagerFlyCmsTools();
    }

    protected function registerFlyCmsResources(): void
    {
        $this->resources = array_merge($this->resources, [
            FlyCmsOverviewResource::class,
            WebsiteGuidelinesResource::class,
            PageGuidelinesResource::class,
            MenuGuidelinesResource::class,
            FileGuidelinesResource::class,
            TagGuidelinesResource::class,
        ]);
    }

    protected function registerArticleTools(): void
    {
        $this->tools = array_merge($this->tools, [
            CreateArticleTool::class,
            ListArticlesTool::class,
            PublishArticleTool::class,
            ShowArticleTool::class,
            UpdateArticleContextTool::class,
        ]);
    }

    protected function registerAuthorTools(): void
    {
        $this->tools = array_merge($this->tools, [
            CreateAuthorTool::class,
            ListAuthorsTool::class,
            ShowAuthorTool::class,
            UpdateAuthorContextTool::class,
            UpdateAuthorTool::class,
        ]);
    }

    protected function registerChannelTools(): void
    {
        $this->tools = array_merge($this->tools, [
            CreateChannelTool::class,
            GetChannelConfigSchemaTool::class,
            ListChannelsTool::class,
            ShowChannelTool::class,
            UpdateChannelTool::class,
        ]);
    }

    protected function registerClientTools(): void
    {
        $this->tools = array_merge($this->tools, [
            CreateClientTool::class,
            ListClientsTool::class,
            ShowClientTool::class,
            UpdateClientTool::class,
            UpdateClientContextTool::class,
        ]);
    }

    protected function registerGuidelineTools(): void
    {
        $this->tools = array_merge($this->tools, [
            ListGuidelinesTool::class,
            GetGuidelineTool::class,
        ]);
    }

    protected function registerPlatformTools(): void
    {
        $this->tools = array_merge($this->tools, [
            CreatePlatformTool::class, // super only
            ListPlatformsTool::class,
            UpdatePlatformTool::class, // super only
            UpdatePlatformConfigTool::class, // super only
        ]);
    }

    protected function registerPlatformManagerFlyCmsTools(): void
    {
        $this->tools = array_merge($this->tools, [

            // Website tools
            CreateWebsiteTool::class,
            ShowWebsiteTool::class,
            UpdateWebsiteTool::class,

            // Meta tools
            ListMetaTool::class,
            PutMetaTool::class,
            DeleteMetaTool::class,

            // Domain tools
            ShowDomainTool::class,
            ListDomainsTool::class,

            // Tag tools
            CreateTagTool::class,
            ShowTagTool::class,
            UpdateTagTool::class,
            ListTagsTool::class,
            DeleteTagTool::class,

            // Menu tools
            CreateMenuTool::class,
            ShowMenuTool::class,
            UpdateMenuTool::class,
            ListMenusTool::class,
            DeleteMenuTool::class,

            // Page tools
            CreatePageTool::class,
            ShowPageTool::class,
            UpdatePageTool::class,
            ListPagesTool::class,
            DeletePageTool::class,

            // File tools
            CreateFileTool::class,
            ShowFileTool::class,
            UpdateFileTool::class,
            ListFilesTool::class,
            DeleteFileTool::class,

            // Theme tools
            ListThemesTool::class,
            ShowThemeTool::class,
        ]);
    }
}
