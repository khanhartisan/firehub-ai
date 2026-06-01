<?php

namespace App\Models;

use App\Contracts\Mcp\StructuredMcpResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Channel extends Model implements ShouldCascade, StructuredMcpResource
{
    use Cascades;

    protected $casts = [
        'config' => 'array',
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return new CascadeDetails($this->publications());
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The unique identifier'),
            'client_id' => $schema->string()->description('The client this channel belongs to'),
            'platform_id' => $schema->string()->description('The platform this channel publishes to'),
            'name' => $schema->string()->description('Channel display name'),
            'config' => $schema
                ->object([])
                ->description('Channel-specific configuration')
                ->nullable(),
            'publications_count' => $schema->integer()->description('Number of publications on this channel'),
            'created_at' => $schema->string()->description('Channel created at'),
            'updated_at' => $schema->string()->description('Channel updated at'),
        ];
    }

    public function toMcpStructuredData(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'platform_id' => $this->platform_id,
            'name' => $this->name,
            'config' => $this->config ?? [],
            'publications_count' => $this->publications_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
