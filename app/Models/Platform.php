<?php

namespace App\Models;

use App\Enums\PlatformType;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $casts = [
        'type' => PlatformType::class,
        'config' => 'array',
        'channels_count' => 'integer',
    ];

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }
}
