<?php

namespace App\Mcp\Support\Guidelines;

use Laravel\Mcp\Server\Resource;

final class McpResourceReference
{
    /**
     * @param  class-string<Resource>  $resourceClass
     * @return array{title: string, uri: string}
     */
    public static function fromResourceClass(string $resourceClass): array
    {
        /** @var Resource $resource */
        $resource = new $resourceClass;

        return [
            'title' => $resource->title(),
            'uri' => $resource->uri(),
        ];
    }
}
