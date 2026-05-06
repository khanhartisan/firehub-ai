<?php

namespace Tests\Unit\Models;

use App\Contracts\Model\Client\Context;
use App\Models\Client;
use Tests\TestCase;

class ClientContextCastTest extends TestCase
{
    public function test_it_dehydrates_and_hydrates_context_perfectly(): void
    {
        $context = (new Context)
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
        $client->context = $context;

        $this->assertIsString($client->getAttributes()['context']);
        $this->assertSame($context->toArray(), json_decode($client->getAttributes()['context'], true));

        $rehydrated = new Client;
        $rehydrated->setRawAttributes([
            'context' => $client->getAttributes()['context'],
        ], true);

        $this->assertInstanceOf(Context::class, $rehydrated->context);
        $this->assertSame($context->toArray(), $rehydrated->context->toArray());
    }

    public function test_it_accepts_array_payload_and_casts_to_context(): void
    {
        $payload = [
            'name' => [
                'description' => 'Brand name of the website',
                'value' => 'Acme AI',
                'weight' => 0.2
            ],
            'meta' => [
                'description' => 'Dynamic, non-standard contextual signals.',
                'value' => [
                    'region' => 'US',
                ],
                'weight' => 0.5,
            ],
        ];

        $client = new Client;
        $client->context = $payload;

        $this->assertInstanceOf(Context::class, $client->context);
        $this->assertSame($payload, $client->context->toArray());
    }
}

