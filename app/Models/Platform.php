<?php

namespace App\Models;

use App\Contracts\Mcp\StructuredMcpResource;
use App\Contracts\PlatformManager\PlatformManager;
use App\Enums\PlatformType;
use App\Facades\Platforms\FlyCms;
use Exception;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Platform extends Model implements ShouldCascade, StructuredMcpResource
{
    use Cascades;

    protected $casts = [
        'type' => PlatformType::class,
        'config' => 'array',
        'channels_count' => 'integer',
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->channels()),
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    /**
     * @throws Exception
     */
    public function getPlatformManager(): PlatformManager
    {
        if (!$platformManager = match ($this->type) {
            PlatformType::FLYCMS => FlyCms::driver(),
            default => null,
        }) {
            throw new Exception('Platform manager not found for: '.$this->type?->value);
        }

        return $platformManager;
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The unique identifier'),
            'type' => $schema
                ->string()
                ->enum(PlatformType::class)
                ->description('Platform type'),
            'name' => $schema->string()->description('Platform name'),
            'channels_count' => $schema->integer()->description('Number of channels using this platform'),
            'created_at' => $schema->string()->description('Platform created at'),
            'updated_at' => $schema->string()->description('Platform updated at'),
        ];
    }

    public function toMcpStructuredData(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'channels_count' => $this->channels_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
