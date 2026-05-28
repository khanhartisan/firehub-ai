<?php

namespace App\Mcp\Tools\ClientTools;

use App\Models\Client;
use App\Models\User;
use App\Utils\Str;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show the list of clients that belong to the current user.')]
class ListClientsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Collection<Client> $clients */
        $clients = $user->clients;

        if (!$clients or $clients->isEmpty()) {
            return Response::error('No clients found.');
        }

        return Response::make(
            Response::text('Found '.$clients->count().' '.Str::plural('client', $clients->count()))
        )->withStructuredContent([
            'clients' => $clients
                ->map(fn (Client $client) => $client->toMcpStructuredData())
                ->values()
                ->toArray()
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            //
        ];
    }
}
