<?php

namespace App\Mcp\Tools\ClientTools;

use App\Enums\Language;
use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an existing client.')]
class UpdateClientTool extends Tool
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
            'name' => ['sometimes', 'required', 'string', 'min:5', 'max:50'],
            'language' => ['sometimes', 'nullable', Rule::enum(Language::class)],
        ]);

        if (! $request->exists('name') && ! $request->exists('language')) {
            return Response::error('Provide at least one field to update (name or language).');
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        /** @var Client|null $client */
        $client = $user->clients()->where('clients.id', $request->get('client_id'))->first();

        if ($client === null) {
            return Response::error('Client not found or you do not have access to this client.');
        }

        if ($request->exists('name')) {
            $client->name = $request->get('name');
        }

        if ($request->exists('language')) {
            $client->language = $request->get('language');
        }

        DB::transaction(function () use ($client): void {
            $client->save();
        });

        $client->refresh();

        return Response::make(Response::text('Successfully updated the client.'))
            ->withStructuredContent($client->toMcpStructuredData());
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
                ->description('The ULID of the client to update')
                ->required(),
            'name' => $schema->string()
                ->description('Client name (for internal display)'),
            'language' => $schema
                ->string()
                ->enum(Language::class)
                ->nullable()
                ->description('Client language'),
        ];
    }
}
