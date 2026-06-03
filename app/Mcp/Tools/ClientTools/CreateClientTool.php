<?php

namespace App\Mcp\Tools\ClientTools;

use App\Enums\Language;
use App\Mcp\Support\McpResponse;
use App\Mcp\Tools\Tool;
use App\Models\Client;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a new client.')]
class CreateClientTool extends Tool
{
    /**
     * Handle the tool request.
     *
     * @throws ValidationException
     */
    public function handle(Request $request): ResponseFactory
    {
        $request->validate([
            'name' => ['required', 'string', 'min:5', 'max:50'],
        ]);

        $client = new Client;
        $client->name = $request->get('name');
        $client->language = $request->get('language');
        DB::transaction(function () use ($client, $request) {
            $client->save();
            $client->users()->attach($request->user());
        });

        return McpResponse::created('client', $client->toMcpStructuredData());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Client name (for internal display)')
                ->required(),
            'language' => $schema
                ->string()
                ->enum(Language::class)
                ->nullable(),
        ];
    }
}
