<?php

namespace App\Mcp\Support;

use Laravel\Mcp\Request;

final class McpRequest
{
    /**
     * @param  list<string>  $fields
     */
    public static function hasAnyField(Request $request, array $fields): bool
    {
        foreach ($fields as $field) {
            if ($request->exists($field)) {
                return true;
            }
        }

        return false;
    }
}
