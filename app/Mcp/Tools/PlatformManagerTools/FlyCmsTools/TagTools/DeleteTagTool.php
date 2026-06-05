<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\TagTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Delete a FlyCMS tag on the website linked to the given channel.')]
class DeleteTagTool extends FlyCmsTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $tagId = (string) $request->get('tag_id');
        $this->resolveTagForChannel($channel, $user, $tagId);

        if (! $this->getFlyCmsManager($channel, $user)->deleteTag($tagId)) {
            throw new McpToolException("Tag [{$tagId}] not found.");
        }

        return McpResponse::textWithStructured(
            "Successfully deleted tag [{$tagId}].",
            ['tag_id' => $tagId],
            ['tag_id' => $tagId],
        );
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel that belongs to a platform with type = flycms')
                ->required(),
            'tag_id' => $schema->string()
                ->description('The FlyCMS tag ID to delete')
                ->required(),
        ];
    }
}
