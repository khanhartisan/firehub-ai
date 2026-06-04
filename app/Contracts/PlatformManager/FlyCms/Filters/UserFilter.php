<?php

namespace App\Contracts\PlatformManager\FlyCms\Filters;

use App\Contracts\PlatformManager\FlyCms\Filter;
use Illuminate\Contracts\JsonSchema\JsonSchema;

class UserFilter extends Filter
{
    public function toJsonSchema(JsonSchema $schema): array
    {
        return [
            'ids' => $schema
                ->string()
                ->nullable()
                ->description('List of user IDs separated by commas'),
            'search' => $schema
                ->string()
                ->nullable()
                ->description('Search users by name'),
            'website_id' => $schema
                ->string()
                ->nullable()
                ->description('Filter users assigned to the given website'),
            'branch_id' => $schema
                ->string()
                ->nullable()
                ->description('Filter users assigned to the given branch'),
            'role_id' => $schema
                ->string()
                ->nullable()
                ->description('Filter users by role ID'),
        ];
    }
}
