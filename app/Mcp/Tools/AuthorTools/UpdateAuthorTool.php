<?php

namespace App\Mcp\Tools\AuthorTools;

use App\Models\Author;
use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an existing author.')]
class UpdateAuthorTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory|Response
    {
        if ($request->has('name')) {
            $request->merge(['name' => trim((string) $request->get('name'))]);
        }

        $request->validate([
            'author_id' => ['required', 'string'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'client_id' => ['sometimes', 'required', 'string'],
        ]);

        if (! $request->exists('name') && ! $request->exists('client_id')) {
            return Response::error('Provide at least one field to update (name or client_id).');
        }

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

        if ($request->exists('client_id')) {
            /** @var Client|null $client */
            $client = $user->clients()->where('clients.id', $request->get('client_id'))->first();

            if ($client === null) {
                return Response::error('Client not found or you do not have access to this client.');
            }

            $author->client()->associate($client);
        }

        if ($request->exists('name')) {
            $author->name = (string) $request->get('name');
        }

        DB::transaction(function () use ($author): void {
            $author->save();
        });

        $author->refresh();

        $data = $author->toMcpStructuredData();

        return Response::make(Response::text('Successfully updated the author:'."\n\n".json_encode($data)))
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
                ->description('The ULID of the author to update')
                ->required(),
            'name' => $schema->string()
                ->description('Author display name'),
            'client_id' => $schema->string()
                ->description('The ULID of the client to move the author to'),
        ];
    }
}
