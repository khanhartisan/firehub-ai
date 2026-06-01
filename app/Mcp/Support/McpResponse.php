<?php

namespace App\Mcp\Support;

use App\Utils\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

final class McpResponse
{
    public static function resource(string $heading, array $data): ResponseFactory
    {
        return Response::make(Response::text($heading."\n\n".json_encode($data)))
            ->withStructuredContent($data);
    }

    public static function created(string $entity, array $data): ResponseFactory
    {
        return self::resource("Successfully created a new {$entity}:", $data);
    }

    public static function updated(string $entity, array $data): ResponseFactory
    {
        return self::resource("Successfully updated the {$entity}:", $data);
    }

    public static function details(string $entity, array $data): ResponseFactory
    {
        return self::resource("{$entity} details:", $data);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function list(
        string $singularLabel,
        array $items,
        string $structuredKey,
        ?string $message = null,
    ): ResponseFactory {
        $count = count($items);
        $heading = $message ?? ('Found '.$count.' '.Str::plural($singularLabel, $count).':');

        return Response::make(Response::text($heading."\n\n".json_encode($items)))
            ->withStructuredContent([
                $structuredKey => $items,
            ]);
    }

    /**
     * @param  array<string, mixed>  $structuredContent
     */
    public static function textWithStructured(string $heading, array $payload, array $structuredContent): ResponseFactory
    {
        return Response::make(Response::text($heading."\n\n".json_encode($payload)))
            ->withStructuredContent($structuredContent);
    }
}
