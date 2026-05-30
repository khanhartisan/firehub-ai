<?php

namespace App\Mcp\Tools\ArticleTools;

use App\Models\Article;
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

#[Description('Create a new article for a client.')]
class CreateArticleTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory|Response
    {
        $request->validate([
            'client_id' => ['required', 'string'],
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

        $article = new Article;
        $article->client()->associate($client);

        DB::transaction(function () use ($article): void {
            $article->save();
        });

        $article->refresh();

        $data = $article->toMcpStructuredData();

        return Response::make(Response::text('Successfully created a new article:'."\n\n".json_encode($data)))
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
                ->description('The ULID of the client to create the article for')
                ->required(),
        ];
    }
}
