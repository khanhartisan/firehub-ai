<?php

namespace App\Contracts\Synthesizer\Illustration;

use App\Contracts\Serializable;

interface Illustratable extends Serializable
{
    public function getIllustrationContent(): string;
}