<?php

namespace App\Models;

use App\Casts\ClientContextCast;
use App\Contracts\Mcp\StructuredMcpResource;
use App\Contracts\Model\Client\Context;
use App\Enums\Language;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use KhanhArtisan\LaravelBackbone\RelationCascade\CascadeDetails;
use KhanhArtisan\LaravelBackbone\RelationCascade\Cascades;
use KhanhArtisan\LaravelBackbone\RelationCascade\ShouldCascade;

class Client extends Model implements ShouldCascade, StructuredMcpResource
{
    use Cascades;

    protected $casts = [
        'language' => Language::class,
        'context' => ClientContextCast::class,
    ];

    public function getCascadeDetails(): CascadeDetails|array
    {
        return [
            new CascadeDetails($this->articles()),
            new CascadeDetails($this->authors()),
            new CascadeDetails($this->hasMany(ClientUser::class)),
        ];
    }

    public function autoForceDeleteWhenAllRelationsAreDeleted(): bool
    {
        return true;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(ClientUser::class)
            ->as('client_user');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function authors(): HasMany
    {
        return $this->hasMany(Author::class);
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The unique identifier'),
            'name' => $schema
                ->string()
                ->description('Client name (for internal display only)'),
            'language' => $schema
                ->string()
                ->nullable()
                ->description('Client language'),
            'context' => $schema
                ->object(new Context()->toJsonSchema($schema))
                ->description('Client general context')
                ->nullable(),
            'created_at' => $schema->string()->description('Client created at'),
            'updated_at' => $schema->string()->description('Client updated at'),
        ];
    }

    public function toMcpStructuredData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'language' => $this->language,
            'context' => (object) ($this->context?->toArray() ?? new \StdClass()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
