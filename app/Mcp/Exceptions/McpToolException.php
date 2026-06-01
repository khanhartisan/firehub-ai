<?php

namespace App\Mcp\Exceptions;

use RuntimeException;

class McpToolException extends RuntimeException
{
    public static function make(string $message): never
    {
        throw new self($message);
    }
}
