<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function clients(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
