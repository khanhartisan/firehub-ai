<?php

namespace App\Mcp\Support\PlatformManager\FlyCms;

use App\Contracts\PlatformManager\FlyCms\ProvidesFlyCmsGuidelines;
use App\Mcp\Support\Guidelines\GuidelinesBreadcrumb;
use App\Mcp\Support\Guidelines\JsonSchemaMarkdown;
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
        }

        foreach ($guidelinesClass::sections() as $section) {
            $sections[] = '## '.$section['title']."\n\n".$section['content'];
        }

        return implode("\n\n", array_filter($sections, fn (string $section): bool => trim($section) !== ''));
    }
}
