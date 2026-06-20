<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MetaTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Delete a FlyCMS meta entry on the website linked to the given channel.')]
class DeleteMetaTool extends FlyCmsTool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $metaId = (string) $request->get('meta_id');
        $this->resolveMetaForChannel($channel, $user, $metaId);

        if (! $this->getFlyCmsManager($channel, $user)->deleteMeta($metaId)) {
            throw new McpToolException("Meta [{$metaId}] not found.");
        }

        return McpResponse::textWithStructured(
            "Successfully deleted meta [{$metaId}].",
            ['meta_id' => $metaId],
            ['meta_id' => $metaId],
        );
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
            'meta_id' => $schema->string()
                ->description('The FlyCMS meta ID to delete')
                ->required(),
        ];
    }
}
