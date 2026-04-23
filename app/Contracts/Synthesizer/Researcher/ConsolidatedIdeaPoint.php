<?php

namespace App\Contracts\Synthesizer\Researcher;

use App\Contracts\CommonData\Concerns\HasConflicts;

final class ConsolidatedIdeaPoint extends IdeaPoint
{
    use HasConflicts;
}