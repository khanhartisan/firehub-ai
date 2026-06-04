<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData;

use App\Contracts\PlatformManager\FlyCms\MutationData;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class CreatePostData extends MutationData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
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
            'content' => $schema
                ->string()
                ->nullable()
                ->max(65535)
                ->description('Post content in HTML format'),
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
            'visibility' => $schema
                ->string()
                ->required()
                ->enum(['public', 'private']),
            'restriction' => $schema
                ->integer()
                ->nullable()
                ->min(0)
                ->max(2)
                ->description('0: No restriction, 1: Restricted to show in the tag pages only, 2: Not showing anywhere in the website but accessible by URL'),
            'tag_ids' => $schema
                ->array()
                ->items($schema->string()->description('Tag ID'))
                ->nullable()->max(50)
                ->description('List of Tag IDs that the post will be attached to')
        ];
    }
}