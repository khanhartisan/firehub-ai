<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\WebsiteMutationData;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdateWebsiteData extends CreateWebsiteData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        $data = parent::toJsonSchema($schema);
        $data['status'] = $schema->string()->nullable()->enum(['active', 'inactive']);
        $data['name'] = $schema->string()->nullable()->description('Website name (for internal display)');

        return $data;
    }
}
