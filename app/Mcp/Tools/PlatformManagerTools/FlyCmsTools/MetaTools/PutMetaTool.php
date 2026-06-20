<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\MetaTools;

use App\Contracts\PlatformManager\FlyCms\Exceptions\FlyCmsException;
use App\Contracts\PlatformManager\FlyCms\MutationData\MetaMutationData\PutMetaData;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use App\Utils\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Upsert FlyCMS meta entries on the website linked to the given channel.')]
class PutMetaTool extends FlyCmsTool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $putPayload = $request->get('put_meta_data');

        if (! is_array($putPayload) || $putPayload === []) {
            throw new McpToolException('Provide put_meta_data with at least one meta entry.');
        }

        $flycms = $this->getFlyCmsManager($channel, $user);

        try {
            $putPayload['metable_type'] = 'website';
            $putPayload['metable_id'] = $this->requireFlyCmsWebsiteId($channel);
            $putMetaData = (new PutMetaData)->setData($putPayload);
            $metaResources = $flycms->putMeta($putMetaData);

            $metaData = array_map(
                static fn ($entry) => $entry->toMcpStructuredData(),
                $metaResources
            );

            $count = count($metaData);
            $message = 'Successfully upserted '.$count.' meta '.Str::plural('entry', $count).':';

            return McpResponse::list('meta entry', $metaData, 'meta', $message);
        } catch (FlyCmsException|InvalidArgumentException $e) {
            throw new McpToolException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = (new PutMetaData)->toJsonSchema($schema);
        unset($properties['metable_type'], $properties['metable_id']);

        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel that belongs to a platform with type = flycms')
                ->required(),
            'put_meta_data' => $schema->object($properties)
                ->required()
                ->description('Meta upsert payload (metable_type and metable_id are set from the channel reference)'),
        ];
    }
}
