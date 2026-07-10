<?php

namespace App\Mcp\Tools\GuidelineTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\Guidelines\GuidelineCatalog;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\Tool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Get the full markdown content for a guideline/overview document by URI, title, name, or resource class.')]
class GetGuidelineTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'identifier' => ['required', 'string'],
        ]);

        $identifier = (string) $request->get('identifier');
        $entry = GuidelineCatalog::findByIdentifier($identifier);

        if ($entry === null) {
            throw new McpToolException(
                'Guideline resource not found for identifier ['.$identifier.']. Use `list-guidelines` to discover available resources.'
            );
        }

        $content = GuidelineCatalog::readContent($entry['resource_class']);
        unset($entry['resource_class']);

        return McpResponse::details('Guideline resource', [
            'identifier' => $identifier,
            'resource' => $entry,
            'content' => $content,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'identifier' => $schema->string()
                ->description('Resource URI, title, resource name, short class name, or FQCN. Example: platform-manager://flycms/website-guidelines')
                ->required(),
        ];
    }
}
