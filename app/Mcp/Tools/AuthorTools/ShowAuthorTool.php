<?php

namespace App\Mcp\Tools\AuthorTools;

use App\Models\Author;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show details of an existing author.')]
class ShowAuthorTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory|Response
    {
        $request->validate([
            'author_id' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        /** @var Author|null $author */
        $author = Author::query()
            ->where('authors.id', $request->get('author_id'))
            ->whereHas('client.users', fn ($query) => $query->where('users.id', $user->id))
            ->first();

        if ($author === null) {
            return Response::error('Author not found or you do not have access to this author.');
        }

        $data = $author->toMcpStructuredData();

        return Response::make(Response::text('Author details:'."\n\n".json_encode($data)))
            ->withStructuredContent($data);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'author_id' => $schema->string()
                ->description('The ULID of the author to show')
                ->required(),
        ];
    }
}
