<?php

namespace App\Models;

use App\Casts\PublishingScheduleContextCast;
use App\Enums\PublishingScheduleStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

// TODO: Continue finishing the publishing schedule
class PublishingSchedule extends Model implements ShouldCascade
{
    use Cascades;

    protected $casts = [
        'status' => PublishingScheduleStatus::class,
        'context' => PublishingScheduleContextCast::class,
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->publishingScheduleRelations())
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function publishingScheduleRelations(): HasMany
    {
        return $this->hasMany(PublishingScheduleRelation::class);
    }
}
