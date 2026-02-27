<?php

namespace App\Contracts\VerticalResolver;

interface VerticalResolver
{
    /**
     * Receive the content and a list of existing verticals.
     * Return a list of VerticalMatch
     *
     * @param string $content
     * @param Vertical[] $verticals
     * @return VerticalMatch[]
     */
    public function resolve(string $content, array $verticals): array;

    /**
     * Receive the content and a list of existing verticals.
     * Propose new verticals if necessary or return an empty array.
     *
     * @param string $content
     * @param Vertical[] $verticals
     * @return Vertical[]
     */
    public function propose(string $content, array $verticals): array;
}