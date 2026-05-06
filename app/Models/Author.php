<?php

namespace App\Models;

use App\Casts\AuthorContextCast;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    protected $casts = [
        'context' => AuthorContextCast::class,
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function clients(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
