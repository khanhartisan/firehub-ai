<?php

namespace Tests\Unit\Models;

use App\Contracts\Model\Client\GeneralContext;
use App\Models\Client;
use Tests\TestCase;

class ClientGeneralContextCastTest extends TestCase
{
    public function test_it_dehydrates_and_hydrates_general_context_perfectly(): void
    {
        $context = (new GeneralContext)
            ->setName('Acme AI')
            ->setDescription('AI automation consulting platform.')
            ->setToneOfVoice('Clear and practical')
            ->setIndustry('Technology')
            ->setNiches(['Workflow Automation', 'AI Agents'])
            ->setCoreMission('Help teams automate repetitive work.')
            ->setGuidelines(['Avoid hype', 'Use concrete examples'])
            ->setMeta([
                'region' => 'US',
                'score' => 0.93,
                'tags' => ['automation', 'productivity'],
            ]);

        $client = new Client;
        $client->general_context = $context;

        $this->assertIsString($client->getAttributes()['general_context']);
        $this->assertSame($context->toArray(), json_decode($client->getAttributes()['general_context'], true));

        $rehydrated = new Client;
        $rehydrated->setRawAttributes([
            'general_context' => $client->getAttributes()['general_context'],
        ], true);

        $this->assertInstanceOf(GeneralContext::class, $rehydrated->general_context);
        $this->assertSame($context->toArray(), $rehydrated->general_context->toArray());
    }

    public function test_it_accepts_array_payload_and_casts_to_general_context(): void
    {
        $payload = [
            'name' => [
                'description' => 'Brand name of the website',
                'value' => 'Acme AI',
            ],
            'meta' => [
                'description' => 'Dynamic, non-standard contextual signals.',
                'value' => [
                    'region' => 'US',
                ],
            ],
        ];

        $client = new Client;
        $client->general_context = $payload;

        $this->assertInstanceOf(GeneralContext::class, $client->general_context);
        $this->assertSame($payload, $client->general_context->toArray());
    }
}

