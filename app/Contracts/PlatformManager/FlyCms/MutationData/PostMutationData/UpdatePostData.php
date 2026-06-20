<?php

namespace App\Contracts\PlatformManager\FlyCms\MutationData\PostMutationData;

use Illuminate\Contracts\JsonSchema\JsonSchema;

class UpdatePostData extends CreatePostData
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        $data = parent::toJsonSchema($schema);

        unset($data['branch_id'], $data['website_id'], $data['code']);

        $data['slug'] = $schema
            ->string()
            ->nullable()
            ->description('Post URL slug in kebab-case');

        $data['title'] = $schema
            ->string()
            ->nullable()
            ->max(255)
            ->description('Post title');

        $data['content'] = $schema
            ->object($this->updateContentJsonSchema($schema))
            ->nullable()
            ->description('Localized post body content');

        return $data;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function updateContentJsonSchema(JsonSchema $schema): array
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
                ->nullable()
                ->max(65536)
                ->description('Post body in liquid template format'),
        ];
    }
}
