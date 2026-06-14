<?php

namespace App\Models;

use App\Contracts\Mcp\StructuredMcpResource;
use App\Enums\PublicationStatus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Publication extends Model implements StructuredMcpResource
{
    protected $casts = [
        'status' => PublicationStatus::class,
        'meta' => 'array',
        'published_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function publishable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The unique ULID identifier'),
            'channel_id' => $schema->string()->description('The channel this publication belongs to'),
            'publishable_type' => $schema->string()->description('The type of publishable resource'),
            'publishable_id' => $schema->string()->description('The ID of the publishable resource'),
            'title' => $schema->string()->description('Publication title')->nullable(),
            'description' => $schema->string()->description('Publication description')->nullable(),
            'status' => $schema
                ->integer()
                ->description(
                    'Publication status. '
                    .collect(PublicationStatus::cases())
                        ->map(fn (PublicationStatus $status) => $status->value.': '.$status->name)
                        ->join(', ')
                ),
            'reference' => $schema->string()->description('External platform reference')->nullable(),
            'published_at' => $schema->string()->description('When the publication was published')->nullable(),
            'created_at' => $schema->string()->description('Publication created at'),
            'updated_at' => $schema->string()->description('Publication updated at'),
        ];
    }

    public function toMcpStructuredData(): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channel_id,
            'publishable_type' => $this->publishable_type,
            'publishable_id' => $this->publishable_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value,
            'reference' => $this->reference,
            'published_at' => $this->published_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
