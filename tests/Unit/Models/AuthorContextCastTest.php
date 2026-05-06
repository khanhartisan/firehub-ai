<?php

namespace Tests\Unit\Models;

use App\Contracts\Model\Author\AuthorContext;
use App\Contracts\Model\Author\AuthorContexts\CognitiveContext;
use App\Models\Author;
use Tests\TestCase;

class AuthorContextCastTest extends TestCase
{
    public function test_it_dehydrates_and_hydrates_context_perfectly(): void
    {
        $context = (new AuthorContext)
            ->setCognitiveContext(
                (new CognitiveContext)
                    ->setCoreValues(['Pragmatism', 'Meritocracy'])
                    ->setWorldview('Growth comes from disciplined experimentation.')
                    ->setSourceOfTruth('Data-backed outcomes')
            );

        $author = new Author;
        $author->context = $context;

        $this->assertIsString($author->getAttributes()['context']);
        $this->assertSame($context->toArray(), json_decode($author->getAttributes()['context'], true));

        $rehydrated = new Author;
        $rehydrated->setRawAttributes([
            'context' => $author->getAttributes()['context'],
        ], true);

        $this->assertInstanceOf(AuthorContext::class, $rehydrated->context);
        $this->assertSame($context->toArray(), $rehydrated->context->toArray());
    }

    public function test_it_accepts_array_payload_and_casts_to_context(): void
    {
        $payload = [
            'cognitive_context' => [
                'description' => 'Defines the core belief system and logical processing of the author. This prevents the content from falling into the "neutrality trap".',
                'value' => [
                    'worldview' => [
                        'description' => 'A dense, 2-3 sentence statement defining the author\'s fundamental lens on reality.',
                        'value' => 'Compounding progress beats short-term wins.',
                    ],
                ],
            ],
        ];

        $author = new Author;
        $author->context = $payload;

        $this->assertInstanceOf(AuthorContext::class, $author->context);
        $this->assertSame($payload, $author->context->toArray());
    }
}
