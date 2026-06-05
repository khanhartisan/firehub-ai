<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\DomainTools;

use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Show a FlyCMS domain by ID for the website linked to the given channel.')]
class ShowDomainTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $domainId = (string) $request->get('domain_id');
        $domainData = $this->resolveDomainForChannel($channel, $user, $domainId);

        return McpResponse::details('Domain', $domainData->toMcpStructuredData());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel that belongs to a platform with type = flycms')
                ->required(),
            'domain_id' => $schema->string()
                ->description('The FlyCMS domain ID to show')
                ->required(),
        ];
    }
}
