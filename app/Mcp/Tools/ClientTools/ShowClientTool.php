<?php

namespace App\Mcp\Tools\ClientTools;

use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Show details of an existing client.')]
class ShowClientTool extends Tool
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

        $data = $client->toMcpStructuredData();

        return Response::make(Response::text('Client details:'."\n\n".json_encode($data)))
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
                ->description('The ULID of the client to show')
                ->required(),
        ];
    }
}
