<?php

namespace App\Services\Synthesizer\Illustration\Illustrator;

use App\Concerns\Describable;
use App\Concerns\Identifiable;
use App\Contracts\Synthesizer\Illustration\Illustrator;

abstract class IllustratorService implements Illustrator
{
    use Describable;
    use Identifiable;
}

