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

#[Description('Create a new author for a client.')]
class CreateAuthorTool extends Tool
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
            'client_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        /** @var Client|null $client */
        $client = $user->clients()->where('clients.id', $request->get('client_id'))->first();

        if ($client === null) {
            return Response::error('Client not found or you do not have access to this client.');
        }

        $author = new Author;
        $author->client()->associate($client);
        $author->name = (string) $request->get('name');

        DB::transaction(function () use ($author): void {
            $author->save();
        });

        $author->refresh();

        $data = $author->toMcpStructuredData();

        return Response::make(Response::text('Successfully created a new author:'."\n\n".json_encode($data)))
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
            'client_id' => $schema->string()
                ->description('The ULID of the client to create the author for')
                ->required(),
            'name' => $schema->string()
                ->description('Author display name')
                ->required(),
        ];
    }
}
