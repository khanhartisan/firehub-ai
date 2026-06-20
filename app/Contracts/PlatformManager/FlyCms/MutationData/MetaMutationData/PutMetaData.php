<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\MetaMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use App\Contracts\PlatformManager\FlyCms\Resources\WebsiteResource;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\WebsiteGuidelinesResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class PutMetaData extends MutationData
{
    protected string $websiteGuidelinesResourceName;

    public function toJsonSchema(JsonSchema $schema): array
    {
        $guidelines = $this->getWebsiteGuidelinesResourceName();
        $websiteMetaKeys = array_keys(WebsiteResource::getMetaSchema($schema));

        return [
            'metable_type' => $schema
                ->string()
                ->required()
                ->enum(['website'])
                ->description('Parent resource type. Use "website" for website meta.'),
            'metable_id' => $schema
                ->string()
                ->required()
                ->description('Parent resource ID (e.g. website ID from channel reference)'),
            'meta' => $schema
                ->array()
                ->items(
                    $schema->object([
                        'key' => $schema
                            ->string()
                            ->required()
                            ->enum($websiteMetaKeys)
                            ->description('Meta key. See resource: '.$guidelines),
                        'value' => $schema
                            ->string()
                            ->required()
                            ->description('Meta value. SEO keys may use Liquid syntax. See resource: '.$guidelines),
                    ])
                )
                ->required()
                ->min(1)
                ->max(50)
                ->description('Meta entries to upsert.'),
        ];
    }

    protected function getWebsiteGuidelinesResourceName(): string
    {
        return $this->websiteGuidelinesResourceName ??= new WebsiteGuidelinesResource()->name();
    }
}
