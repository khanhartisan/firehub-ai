<?php

namespace App\Mcp\Support;

use App\Mcp\Exceptions\McpToolException;
use App\Models\Article;
use App\Models\Author;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Laravel\Mcp\Request;

final class McpAuthorization
{
    public static function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new McpToolException('Unauthenticated.');
        }

        return $user;
    }

    public static function client(User $user, string $clientId): Client
    {
        /** @var Client|null $client */
        $client = $user->clients()->where('clients.id', $clientId)->first();

        if ($client === null) {
            throw new McpToolException('Client not found or you do not have access to this client.');
        }

        return $client;
    }

    public static function assertClientAccess(User $user, string $clientId): void
    {
        if (! $user->clients()->where('clients.id', $clientId)->exists()) {
            throw new McpToolException('Client not found or you do not have access to this client.');
        }
    }

    public static function author(User $user, string $authorId): Author
    {
        /** @var Author|null $author */
        $author = Author::query()
            ->where('authors.id', $authorId)
            ->accessibleBy($user)
            ->first();

        if ($author === null) {
            throw new McpToolException('Author not found or you do not have access to this author.');
        }

        return $author;
    }

    public static function platform(string $platformId): Platform
    {
        /** @var Platform|null $platform */
        $platform = Platform::query()->find($platformId);

        if ($platform === null) {
            throw new McpToolException('Platform not found.');
        }

        return $platform;
    }

    public static function article(User $user, string $clientId, string $articleId): Article
    {
        self::assertClientAccess($user, $clientId);

        /** @var Article|null $article */
        $article = Article::query()
            ->where('client_id', $clientId)
            ->where('id', $articleId)
            ->first();

        if ($article === null) {
            throw new McpToolException('Article not found or you do not have access to this article.');
        }

        return $article;
    }
}
