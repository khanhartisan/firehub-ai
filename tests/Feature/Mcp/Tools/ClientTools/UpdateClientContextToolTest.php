<?php

namespace Tests\Feature\Mcp\Tools\ClientTools;

use App\Contracts\Model\Client\Context;
use App\Mcp\Servers\AppServer;
use App\Mcp\Tools\ClientTools\UpdateClientContextTool;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateClientContextToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_scalar_context_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(UpdateClientContextTool::class, [
            'client_id' => $client->id,
            'description' => 'AI automation consulting platform.',
            'industry' => 'Technology',
            'tone_of_voice' => 'Clear and practical',
        ]);

        $response
            ->assertOk()
            ->assertSee('Successfully updated the client context')
            ->assertName('update-client-context-tool')
            ->assertDescription('Update the editorial context of an existing client.')
            ->assertStructuredContent(function ($json): void {
                $json->where('name', 'Acme Corp')->has('context')->etc();
            });

        $client->refresh();
        $this->assertSame('AI automation consulting platform.', $client->context->getDescriptionValue());
        $this->assertSame('Technology', $client->context->getIndustryValue());
        $this->assertSame('Clear and practical', $client->context->getToneOfVoiceValue());
    }

    public function test_merges_with_existing_context_without_overwriting_unmentioned_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');
        $client->context = (new Context)
            ->setDescription('Original description')
            ->setIndustry('Finance');
        $client->save();

        $response = AppServer::actingAs($user)->tool(UpdateClientContextTool::class, [
            'client_id' => $client->id,
            'core_mission' => 'Help teams automate repetitive work.',
        ]);

        $response->assertOk();

        $client->refresh();
        $this->assertSame('Original description', $client->context->getDescriptionValue());
        $this->assertSame('Finance', $client->context->getIndustryValue());
        $this->assertSame('Help teams automate repetitive work.', $client->context->getCoreMissionValue());
    }

    public function test_updates_array_context_fields(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(UpdateClientContextTool::class, [
            'client_id' => $client->id,
            'niches' => ['Workflow Automation', 'AI Agents'],
            'guidelines' => ['Avoid hype', 'Use concrete examples'],
            'meta' => ['region' => 'US'],
        ]);

        $response->assertOk();

        $client->refresh();
        $this->assertSame(['workflow automation', 'ai agents'], $client->context->getNichesValue());
        $this->assertSame(['Avoid hype', 'Use concrete examples'], $client->context->getGuidelinesValue());
        $this->assertSame(['region' => 'US'], $client->context->getMetaValue());
    }

    public function test_updates_audience_contexts(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(UpdateClientContextTool::class, [
            'client_id' => $client->id,
            'audience_contexts' => [
                [
                    'name' => 'SMB Operations Leads',
                    'description' => 'Leads juggling throughput and quality.',
                    'knowledge_level' => 'intermediate',
                ],
            ],
        ]);

        $response->assertOk();

        $client->refresh();
        $audiences = $client->context->getAudienceContextsValue();
        $this->assertIsArray($audiences);
        $this->assertCount(1, $audiences);

        $firstAudience = $audiences[0];
        $this->assertIsArray($firstAudience);
        $this->assertSame('SMB Operations Leads', $firstAudience['name']['value']);
        $this->assertSame('intermediate', $firstAudience['knowledge_level']['value']);
    }

    public function test_validation_fails_when_client_id_is_missing(): void
    {
        $user = User::factory()->create();

        $response = AppServer::actingAs($user)->tool(UpdateClientContextTool::class, [
            'description' => 'Missing client id.',
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_when_no_context_fields_to_update(): void
    {
        $user = User::factory()->create();
        $client = $this->attachClient($user, 'Acme Corp');

        $response = AppServer::actingAs($user)->tool(UpdateClientContextTool::class, [
            'client_id' => $client->id,
        ]);

        $response->assertHasErrors(['Provide at least one context field to update.']);
    }

    public function test_returns_error_when_client_is_not_accessible(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = $this->attachClient($otherUser, 'Other Client');

        $response = AppServer::actingAs($user)->tool(UpdateClientContextTool::class, [
            'client_id' => $client->id,
            'description' => 'Should not apply.',
        ]);

        $response->assertHasErrors(['Client not found or you do not have access to this client.']);

        $client->refresh();
        $this->assertNull($client->context->getDescriptionValue());
    }

    public function test_returns_error_when_unauthenticated(): void
    {
        $response = AppServer::tool(UpdateClientContextTool::class, [
            'client_id' => '01J0000000000000000000000',
            'description' => 'No auth.',
        ]);

        $response->assertHasErrors(['Unauthenticated.']);
    }

    private function attachClient(User $user, string $name): Client
    {
        $client = new Client;
        $client->name = $name;
        $client->save();

        $user->clients()->attach($client);

        return $client;
    }
}
