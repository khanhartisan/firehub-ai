<?php

namespace App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\DomainTools;

use App\Contracts\PlatformManager\FlyCms\Filters\DomainFilter;
use App\Mcp\Concerns\ResolvesMcpPagination;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FlyCmsTool;
use App\Utils\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List FlyCMS domains for the website linked to the given channel.')]
class ListDomainsTool extends FlyCmsTool
{
    use ResolvesMcpPagination;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAccess::user($request);
        $channel = McpAccess::channel($user, $request->get('channel_id'));
        $this->validateChannel($channel);

        $flycmsWebsiteId = $this->requireFlyCmsWebsiteId($channel);
        $flycms = $this->getFlyCmsManager($channel, $user);

        $pagination = $this->resolvePagination($request);

        $filterPayload = is_array($request->get('domain_filter')) ? $request->get('domain_filter') : [];
        $filterPayload['website_id'] = $flycmsWebsiteId;

        $domainFilter = (new DomainFilter)->setFilterData($filterPayload);

        $domains = $flycms->listDomains($pagination->page, $pagination->perPage, $domainFilter);

        if ($domains === []) {
            throw new McpToolException('No domains found.');
        }

        $domainsData = array_map(
            static fn ($domain) => $domain->toMcpStructuredData(),
            $domains
        );

        $count = count($domainsData);
        $message = 'Found '.$count.' '.Str::plural('domain', $count)
            .$pagination->listMessageSuffix().':';

        return McpResponse::list('domain', $domainsData, 'domains', $message);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = (new DomainFilter)->toJsonSchema($schema);
        unset($properties['website_id']);

        return [
            'channel_id' => $schema->string()
                ->description('The ULID of the channel that belongs to a platform with type = flycms')
                ->required(),
            ...$this->paginationSchemaProperties($schema, 'domains'),
            'domain_filter' => $schema->object($properties)
                ->description('Optional filters when listing domains (scoped to the channel website)'),
        ];
    }
}
