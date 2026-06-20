<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\TagMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use App\Mcp\Resources\PlatformManagerResources\FlyCmsResources\TagGuidelinesResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreateTagData extends MutationData
{
    protected string $tagGuidelinesResourceName;

    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'website_id' => $schema
                ->string()
                ->required()
                ->description('Website ID (get from channel reference)'),
            'thumbnail_file_id' => $schema
                ->string()
                ->nullable()
                ->description('Thumbnail file ID (FlyCMS File ID)'),
            'name' => $schema
                ->string()
                ->required()
                ->description('Tag name'),
            'display_name' => $schema
                ->string()
                ->nullable()
                ->description('Tag display name'),
            'description' => $schema
                ->string()
                ->max(255)
                ->nullable()
                ->description('Tag description'),
            'slug' => $schema
                ->string()
                ->required()
                ->description('Tag slug in kebab-case'),
            'is_featured' => $schema
                ->boolean()
                ->nullable()
                ->default(false)
                ->description('Is this a featured tag not not?'),
            'seo_title' => $schema
                ->string()
                ->nullable()
                ->description('Seo title in liquid template format. See resource: '.$this->getTagGuidelinesResourceName()),
            'seo_description' => $schema
                ->string()
                ->nullable()
                ->description('Seo description in liquid template format. See resource: '.$this->getTagGuidelinesResourceName()),
            'seo_h1' => $schema
                ->string()
                ->nullable()
                ->description('H1 html tag content in liquid template format. See resource: '.$this->getTagGuidelinesResourceName()),
            'content' => $schema
                ->string()
                ->nullable()
                ->description('Tag body content in liquid template format. See resource: '.$this->getTagGuidelinesResourceName()),
        ];
    }

    protected function getTagGuidelinesResourceName(): string
    {
        return $this->tagGuidelinesResourceName ??= new TagGuidelinesResource()->name();
    }

    public function getData(): ?array
    {
        if (!$data = parent::getData()) {
            return $data;
        }

        if (!isset($data['display_name'])) {
            $data['display_name'] = $data['name'];
        }

        return $data;
    }
}