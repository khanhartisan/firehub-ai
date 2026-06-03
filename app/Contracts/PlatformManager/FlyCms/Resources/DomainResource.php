<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class DomainResource extends Resource
{
    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema
                ->string()
                ->description('Domain unique ID'),
            'website_id' => $schema
                ->string()
                ->description('Website ID that the domain is attached to'),
            'is_primary' => $schema
                ->boolean()
                ->description('Whether the domain is primary'),
            'is_alias' => $schema
                ->boolean()
                ->description('Whether the domain is alias. If true, it will work along with the primary domain, if false it will redirect 301 to the primary domain'),
            'status' => $schema
                ->string()
                ->enum(['active', 'inactive']),
            'domain' => $schema
                ->string()
                ->description('The domain (ie: example.com)'),
            'nameservers' => $schema
                ->array()
                ->items($schema->string())
                ->description('Domain nameservers'),
            'is_connected_to_server' => $schema
                ->boolean()
                ->description('Whether the connection to server yet (ready to serve)'),
        ];
    }
}