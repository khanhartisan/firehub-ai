<?php

namespace App\Models;

use App\Casts\AuthorContextCast;
use App\Contracts\Mcp\StructuredMcpResource;
use App\Contracts\Model\Author\AuthorContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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

    public static function getMcpOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The unique identifier'),
            'client_id' => $schema->string()->description('The client this author belongs to'),
            'name' => $schema
                ->string()
                ->nullable()
                ->description('Author display name'),
            'context' => $schema
                ->object(new AuthorContext()->toJsonSchema($schema))
                ->description('Author persona context')
                ->nullable(),
            'created_at' => $schema->string()->description('Author created at'),
            'updated_at' => $schema->string()->description('Author updated at'),
        ];
    }

    public function toMcpStructuredData(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'name' => $this->name,
            'context' => (object) ($this->context?->toArray() ?? new \StdClass()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
