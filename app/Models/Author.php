<?php

namespace App\Models;

use App\Casts\AuthorContextCast;
use App\Contracts\Mcp\StructuredMcpResource;
use App\Contracts\Model\Author\AuthorContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model implements StructuredMcpResource
{
    protected $casts = [
        'context' => AuthorContextCast::class,
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @param  Builder<Author>  $query
     */
    public function scopeAccessibleBy(Builder $query, User $user): void
    {
        $query->whereHas('client.users', fn (Builder $clientUsersQuery) => $clientUsersQuery->where('users.id', $user->id));
    }

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The unique identifier'),
            'client_id' => $schema->string()->description('The client this author belongs to'),
            'name' => $schema
                ->string()
                ->nullable()
                ->description('Author display name'),
            'created_at' => $schema->string()->description('Author created at'),
            'updated_at' => $schema->string()->description('Author updated at'),
        ];
    }

    public static function getMcpDetailOutputSchema(JsonSchema $schema): array
    {
        return [
            ...self::getMcpOutputSchema($schema),
            'context' => $schema
                ->object(new AuthorContext()->toJsonSchema($schema))
                ->description('Author persona context')
                ->nullable(),
        ];
    }

    public function toMcpStructuredData(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'name' => $this->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toMcpDetailStructuredData(): array
    {
        return [
            ...$this->toMcpStructuredData(),
            'context' => (object) ($this->context?->toArray() ?? new \StdClass),
        ];
    }
}
