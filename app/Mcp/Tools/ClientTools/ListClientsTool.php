<?php

namespace App\Mcp\Tools\ClientTools;

use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAuthorization;
use App\Mcp\Support\McpResponse;
use App\Models\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show the list of clients that belong to the current user.')]
class ListClientsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = McpAuthorization::user($request);

        /** @var Collection<int, Client> $clients */
        $clients = $user->clients;

        if ($clients->isEmpty()) {
            throw new McpToolException('No clients found.');
        }

        $clientsData = $clients
            ->map(fn (Client $client) => $client->toMcpStructuredData())
            ->values()
            ->toArray();

        return McpResponse::list('client', $clientsData, 'clients');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            //
        ];
    }
}
