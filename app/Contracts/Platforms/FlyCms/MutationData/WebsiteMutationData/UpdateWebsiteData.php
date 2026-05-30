<?php

namespace App\Contracts\Platforms\FlyCms\MutationData\WebsiteMutationData;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdateWebsiteData extends CreateWebsiteData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        $data = parent::toJsonSchema($schema);
        $data['status']->required(false);

        return $data;
    }
}
