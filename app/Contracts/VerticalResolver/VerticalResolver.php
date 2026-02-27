<?php

namespace App\Contracts\VerticalResolver;

/**
 * Resolves content to existing verticals and optionally proposes new ones.
 *
 * Implementations (e.g. keyword-based or OpenAI-driven) match content against
 * a given list of verticals and return matches with confidence scores, and
 * may suggest new verticals that are not yet in the list.
 */
interface VerticalResolver
{
    /**
     * Match content against existing verticals and return matches with confidence.
     *
     * @param  string  $content  Raw or normalized content (e.g. page text, HTML).
     * @param  Vertical[]  $verticals  List of existing verticals to match against.
     * @return VerticalMatch[]  Matches with vertical identifier and confidence (0–1), sorted by confidence descending.
     */
    public function resolve(string $content, array $verticals): array;

    /**
     * Propose new verticals based on content when none of the existing ones fit well.
     *
     * @param  string  $content  Raw or normalized content to derive suggestions from.
     * @param  Vertical[]  $verticals  List of existing verticals (e.g. to avoid suggesting duplicates).
     * @return Vertical[]  Suggested new verticals (name + optional description), or empty array if none.
     */
    public function propose(string $content, array $verticals): array;
}