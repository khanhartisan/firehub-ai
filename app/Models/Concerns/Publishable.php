<?php

namespace App\Models\Concerns;

use App\Models\Publication;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Publishable
{
    public function publications(): MorphMany
    {
        return $this->morphMany(Publication::class, 'publishable');
    }
}