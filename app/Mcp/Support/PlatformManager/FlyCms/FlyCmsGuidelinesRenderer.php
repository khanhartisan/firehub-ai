<?php

namespace App\Mcp\Support\PlatformManager\FlyCms;

use App\Contracts\PlatformManager\FlyCms\MetableResource;
use App\Contracts\PlatformManager\FlyCms\ProvidesFlyCmsGuidelines;
use App\Mcp\Support\Guidelines\GuidelinesBreadcrumb;
use App\Mcp\Support\Guidelines\JsonSchemaMarkdown;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Mcp\Server\Resource;

final class FlyCmsGuidelinesRenderer
{
    /**
     * @param  class-string<ProvidesFlyCmsGuidelines>  $guidelinesClass
     * @param  class-string<Resource>  $currentResourceClass
     * @param  list<class-string<Resource>>  $breadcrumbParents
     */
    public static function render(
        string $guidelinesClass,
        string $currentResourceClass,
        array $breadcrumbParents = [],
    ): string {
        $sections = [
            GuidelinesBreadcrumb::render($breadcrumbParents, $currentResourceClass),
            '',
            '# '.$guidelinesClass::title(),
            '',
            $guidelinesClass::intro(),
            JsonSchemaMarkdown::mutationFieldTable(
                $guidelinesClass::createMutationDataClass(),
                $guidelinesClass::updateMutationDataClass(),
                $guidelinesClass::excludedMutationFields(),
            ),
        ];

        $resourceClass = $guidelinesClass::resourceClass();
        if ($resourceClass !== null) {
            $sections[] = JsonSchemaMarkdown::resourceOutputTable(
                $resourceClass,
                $guidelinesClass::excludedResourceFields(),
            );

            if (is_subclass_of($resourceClass, MetableResource::class)) {
                $factory = new JsonSchemaTypeFactory;
                $metaSchema = $resourceClass::getMetaSchema($factory);

                $sections[] = JsonSchemaMarkdown::metaFallbackTable(
                    $metaSchema,
                    array_keys($metaSchema),
                    heading: 'Meta fields',
                    intro: 'Site-wide settings returned under `meta` on website responses. SEO values use Liquid template syntax where noted.',
                );
            }
        }

        foreach ($guidelinesClass::sections() as $section) {
            $sections[] = '## '.$section['title']."\n\n".$section['content'];
        }

        return implode("\n\n", array_filter($sections, fn (string $section): bool => trim($section) !== ''));
    }
}
