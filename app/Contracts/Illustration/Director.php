<?php

namespace App\Contracts\Illustration;

interface Director
{
    public function chooseIllustrator(IllustrationContext $context): ?Illustrator;
}