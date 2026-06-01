<?php

namespace App\Mcp\Tools\ClientTools;

use App\Enums\Language;
use App\Mcp\Exceptions\McpToolException;
use App\Mcp\Support\McpAccess;
use App\Mcp\Support\McpResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update an existing client.')]
class UpdateClientTool extends Tool
{
    /**
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'client_id' => ['required', 'string'],
            'name' => ['sometimes', 'required', 'string', 'min:5', 'max:50'],
            'language' => ['sometimes', 'nullable', Rule::enum(Language::class)],
        ]);

        if (! $request->exists('name') && ! $request->exists('language')) {
            throw new McpToolException('Provide at least one field to update (name or language).');
        }

        $user = McpAccess::user($request);
        $client = McpAccess::client($user, (string) $request->get('client_id'));

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

        return McpResponse::updated('client', $client->toMcpStructuredData());
    }

    /**
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
