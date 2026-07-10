<?php

namespace App\Mcp\Support\Guidelines;

use App\Mcp\Resources\GuidelineResource;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Resource;

final class GuidelineCatalog
{
    /**
     * @return list<class-string<Resource>>
     */
    public static function resourceClasses(): array
    {
        $resourceClasses = [];

        foreach (File::allFiles(app_path('Mcp/Resources')) as $file) {
            $path = $file->getPathname();

            if (! str_ends_with($path, '.php')) {
                continue;
            }

            $relative = str_replace(app_path().DIRECTORY_SEPARATOR, '', $path);
            $class = 'App\\'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);

            if (! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, Resource::class)) {
                continue;
            }

            if (! is_subclass_of($class, GuidelineResource::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            /** @var class-string<Resource> $class */
            $resourceClasses[] = $class;
        }

        sort($resourceClasses);

        return array_values(array_unique($resourceClasses));
    }

    /**
     * @return list<array{
     *     name: string,
     *     title: string,
     *     uri: string,
     *     description: string|null,
     *     mime_type: string|null,
     *     type: 'overview'|'guideline'
     * }>
     */
    public static function all(): array
    {
        return array_map(
            static function (string $resourceClass): array {
                /** @var Resource $resource */
                $resource = new $resourceClass;
                $title = $resource->title();

                return [
                    'name' => $resource->name(),
                    'title' => $title,
                    'uri' => $resource->uri(),
                    'description' => method_exists($resource, 'description') ? $resource->description() : null,
                    'mime_type' => method_exists($resource, 'mimeType') ? $resource->mimeType() : null,
                    'type' => str_contains(strtolower($title), 'guideline') ? 'guideline' : 'overview',
                ];
            },
            self::resourceClasses(),
        );
    }

    /**
     * @return array{
     *     resource_class: class-string<Resource>,
     *     name: string,
     *     title: string,
     *     uri: string,
     *     description: string|null,
     *     mime_type: string|null,
     *     type: 'overview'|'guideline'
     * }|null
     */
    public static function findByIdentifier(string $identifier): ?array
    {
        $needle = strtolower(trim($identifier));

        if ($needle === '') {
            return null;
        }

        $entries = self::all();

        foreach ($entries as $entry) {
            if (
                strtolower($entry['uri']) === $needle
                || strtolower($entry['name']) === $needle
                || strtolower($entry['title']) === $needle
            ) {
                return self::hydrateInternalResourceClass($entry);
            }
        }

        foreach ($entries as $entry) {
            if (
                str_contains(strtolower($entry['uri']), $needle)
                || str_contains(strtolower($entry['name']), $needle)
                || str_contains(strtolower($entry['title']), $needle)
            ) {
                return self::hydrateInternalResourceClass($entry);
            }
        }

        return null;
    }

    /**
     * @param  array{name: string, title: string, uri: string, description: string|null, mime_type: string|null, type: 'overview'|'guideline'}  $entry
     * @return array{
     *     resource_class: class-string<Resource>,
     *     name: string,
     *     title: string,
     *     uri: string,
     *     description: string|null,
     *     mime_type: string|null,
     *     type: 'overview'|'guideline'
     * }
     */
    private static function hydrateInternalResourceClass(array $entry): array
    {
        foreach (self::resourceClasses() as $resourceClass) {
            /** @var Resource $resource */
            $resource = new $resourceClass;

            if (strtolower($resource->uri()) === strtolower($entry['uri'])) {
                $entry['resource_class'] = $resourceClass;

                return $entry;
            }
        }

        throw new \RuntimeException('Guideline resource class not found for URI ['.$entry['uri'].'].');
    }

    public static function readContent(string $resourceClass): string
    {
        /** @var Resource $resource */
        $resource = new $resourceClass;
        $response = $resource->handle(new Request);

        return $response->content();
    }
}
