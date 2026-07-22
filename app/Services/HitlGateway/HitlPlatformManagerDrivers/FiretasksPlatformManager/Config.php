<?php

namespace App\Services\HitlGateway\HitlPlatformManagerDrivers\FiretasksPlatformManager;

use App\Contracts\HitlGateway\HitlPlatformConfig;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class Config extends HitlPlatformConfig
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'base_url' => $schema
                ->string()
                ->required()
                ->description('Firetasks platform base url'),
            'api_key' => $schema
                ->string()
                ->required()
                ->description('Firetasks platform API key'),
            'folder_id' => $schema
                ->integer()
                ->required()
                ->description('Firetasks platform folder id to work on'),
            'default_responsible_user_id' => $schema
                ->integer()
                ->required()
                ->description('Firetasks platform default responsible user ID'),
            'note' => $schema
                ->string()
                ->nullable()
                ->description('Firetasks platform note'),
        ];
    }
}