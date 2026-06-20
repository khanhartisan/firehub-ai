<?php

namespace App\Contracts\PlatformManager\FlyCms;

use App\Contracts\Mcp\StructuredMcpResource;
use App\Contracts\ProvidesJsonSchema;

/**
 * Contract for FlyCMS MCP guideline resources.
 *
 * Implementations combine hand-written editorial content with schema-generated
 * field reference tables. The renderer uses mutation and resource classes as
 * the source of truth for payload/response shapes; everything else is static.
 */
interface ProvidesFlyCmsGuidelines
{
    /**
     * Markdown document title shown as the top-level heading.
     */
    public static function title(): string;

    /**
     * Opening prose explaining why this MCP resource exists and when agents
     * should read it before calling related tools.
     */
    public static function intro(): string;

    /**
     * MCP tool classes referenced in the guideline prose.
     *
     * Keys are local aliases (for example `create`, `show`) used when building
     * markdown; values are tool classes whose names are resolved dynamically.
     *
     * @return array<string, class-string<\App\Mcp\Tools\Tool>>
     */
    public static function relatedTools(): array;

    /**
     * FlyCMS mutation schema used for create payloads and as the base for the
     * generated mutation field reference table.
     *
     * @return class-string<ProvidesJsonSchema>
     */
    public static function createMutationDataClass(): string;

    /**
     * FlyCMS mutation schema used for update payloads.
     *
     * Return null when the entity has no update mutation or update guidelines
     * are not applicable.
     *
     * @return class-string<ProvidesJsonSchema>|null
     */
    public static function updateMutationDataClass(): ?string;

    /**
     * FlyCMS resource class whose output schema may be rendered as a response
     * reference table.
     *
     * Return null when response field documentation is not needed for this
     * guideline resource.
     *
     * @return class-string<StructuredMcpResource>|null
     */
    public static function resourceClass(): ?string;

    /**
     * Mutation payload fields omitted from the generated reference table.
     *
     * Use this for values set implicitly by MCP tools (for example `website_id`
     * from the channel reference).
     *
     * @return list<string>
     */
    public static function excludedMutationFields(): array;

    /**
     * Response fields omitted from the generated output reference table.
     *
     * Use this for nested or noisy fields that are not useful in guideline docs.
     *
     * @return list<string>
     */
    public static function excludedResourceFields(): array;

    /**
     * Hand-written editorial sections appended after generated reference tables.
     *
     * Each section is markdown content that cannot be inferred from JSON schema
     * alone, such as Liquid syntax rules, HTML guidance, workflows, or examples.
     *
     * @return list<array{title: string, content: string}>
     */
    public static function sections(): array;
}
