<?php

namespace App\Contracts\PlatformManager\FlyCms\Resources;

use App\Contracts\PlatformManager\FlyCms\Resource;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class AuthorResource extends Resource
{
    public static function resourceNamespace(): string
    {
        return 'website_user';
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Author unique ID'),
            'website_id' => $schema
                ->string()
                ->description('Website ID'),
            'display_name' => $schema->string()->description('Display name'),
            'short_bio' => $schema->string()->description('Short bio in plain text format')->max(255),
            'bio' => $schema->string()->description('Bio in HTML format')->max(65535),
            'public_posts_count' => $schema
                ->integer()
                ->description('Posts count'),

            'seo_title' => $schema
                ->string()
                ->max(255)
                ->nullable()
                ->description('Seo title (liquid template format, author instance provided)'),
            'seo_description' => $schema
                ->string()
                ->nullable()
                ->description('Seo description (liquid template format, author instance provided)'),

            'thumbnail_file_id' => $schema
                ->string()
                ->nullable()
                ->description('Thumbnail file ID (FlyCMS File ID)'),
            'thumbnailFile' => $schema
                ->object(FileResource::getMcpOutputSchema($schema))
                ->nullable()
        ];
    }

    public static function fromArray(array $data): static
    {
        $authorResource = parent::fromArray($data);

        if (isset($data['user']['email'])) {
            $authorResource->set('email', $data['user']['email']);
        }

        return $authorResource;
    }
}