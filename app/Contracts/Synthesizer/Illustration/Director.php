<?php

namespace App\Contracts\Synthesizer\Illustration;

interface Director
{
    public function direct(IllustrationContext $context): IllustrationDirection;

    public function determineIllustrator(
        IllustrationContext $context,
        IllustrationDirection $direction
    ): ?Illustrator;
}