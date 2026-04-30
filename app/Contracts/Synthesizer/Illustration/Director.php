<?php

namespace App\Contracts\Synthesizer\Illustration;

interface Director
{
    /**
     * Planning a list of illustration contexts for the given illustratable object
     *
     * @param Illustratable $illustratable
     * @param int|null $minContexts
     * @param int|null $maxContexts
     * @return IllustrationContext[]
     */
    public function resolveIllustrationContexts(
        Illustratable $illustratable,
        ?int $minContexts = null,
        ?int $maxContexts = null,
    ): array;

    /**
     * Give a detailed illustration direction from the given context
     *
     * @param IllustrationContext $context
     * @return IllustrationDirection
     */
    public function direct(IllustrationContext $context): IllustrationDirection;

    /**
     * Determine which illustrator should handle the job
     *
     * @param IllustrationContext $context
     * @param IllustrationDirection $direction
     * @param Illustrator[] $illustrators
     * @return Illustrator|null
     */
    public function determineIllustrator(
        IllustrationContext $context,
        IllustrationDirection $direction,
        array $illustrators
    ): ?Illustrator;
}