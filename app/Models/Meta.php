<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class Meta extends Model
{
    protected $table = 'meta';

    public function metable(): MorphTo
    {
        return $this->morphTo();
    }
}
