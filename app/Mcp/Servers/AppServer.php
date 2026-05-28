<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ClientTools\CreateClient;
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
        CreateClient::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
