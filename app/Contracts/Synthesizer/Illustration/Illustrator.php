<?php

namespace App\Contracts\Synthesizer\Illustration;

use App\Contracts\Describable;
use App\Contracts\Identifiable;

interface Illustrator extends Describable, Identifiable
{
    public function generate(IllustrationContext $context,
                             IllustrationDirection $direction): IllustrationResult;
}