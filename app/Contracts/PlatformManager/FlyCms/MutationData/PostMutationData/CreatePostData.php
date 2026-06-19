<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreatePostData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'branch_id' => $schema
                ->string()
                ->required()
                ->description('Branch ID (ULID) in FlyCMS'),
            'code' => $schema
                ->string()
                ->required()
                ->max(255)
                ->description('Subject unique code, typically the article ID'),
            'website_id' => $schema
                ->string()
                ->required()
                ->description('Website id, get from channel reference'),
            'slug' => $schema
                ->string()
                ->required()
                ->description('Post URL slug in kebab-case'),
            'title' => $schema
                ->string()
                ->required()
                ->max(255)
                ->description('Post title'),
            'description' => $schema
                ->string()
                ->nullable()
                ->max(255)
                ->description('Post description'),
            'lang' => $schema
                ->string()
                ->nullable()
                ->description('Post language, 2 letter ISO 639-1, or "default"'),
            'thumbnail_file_id' => $schema
                ->string()
                ->nullable()
                ->description('Thumbnail file ID (FlyCMS File ID)'),
            'content' => $schema
                ->object($this->contentJsonSchema($schema))
                ->required()
                ->description('Localized post body content'),
            'seo_title' => $schema
                ->string()
                ->nullable()
                ->max(255)
                ->description('Post SEO title'),
            'seo_description' => $schema
                ->string()
                ->nullable()
                ->max(255)
                ->description('Post SEO description'),
            'note' => $schema
                ->string()
                ->nullable()
                ->max(65535)
                ->description('Internal note'),
            'visibility' => $schema
                ->string()
                ->nullable()
                ->enum(['public', 'private'])
                ->description('Post visibility'),
            'restriction' => $schema
                ->integer()
                ->nullable()
                ->min(0)
                ->max(2)
                ->description('0: No restriction, 1: Restricted to show in the tag pages only, 2: Not showing anywhere in the website but accessible by URL'),
            'tag_ids' => $schema
                ->array()
                ->items($schema->string()->description('Tag ID'))
                ->nullable()
                ->max(20)
                ->description('List of Tag IDs that the post will be attached to'),
            'tag_names' => $schema
                ->array()
                ->items($schema->string()->description('Tag name'))
                ->nullable()
                ->max(20)
                ->description('List of tag names to attach; creates missing tags when tag_ids is not provided'),
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function contentJsonSchema(JsonSchema $schema): array
    {
        return [
            'lang' => $schema
                ->string()
                ->nullable()
                ->description('Content language, 2 letter ISO 639-1, or "default"'),
            'title' => $schema
                ->string()
                ->nullable()
                ->max(255)
                ->description('Content title'),
            'description' => $schema
                ->string()
                ->nullable()
                ->max(255)
                ->description('Content description'),
            'content' => $schema
                ->string()
                ->required()
                ->max(65536)
                ->description('Post body in HTML format'),
        ];
    }
}
