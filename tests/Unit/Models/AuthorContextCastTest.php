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
        $authorId = '01JZKQ8N4W2M5X7Y9ABCDEFGH';
        $context = (new AuthorContext)
            ->setCognitiveContext(
                (new CognitiveContext)
                    ->setCoreValues(['Pragmatism', 'Meritocracy'])
                    ->setWorldview('Growth comes from disciplined experimentation.')
                    ->setSourceOfTruth('Data-backed outcomes')
            );

        $author = new Author;
        $author->setAttribute('id', $authorId);
        $author->context = $context;

        $stored = $author->getAttributes()['context'];
        $this->assertIsString($stored);

        $storedPayload = json_decode($stored, true);
        $this->assertSame('author-ctx-' . $authorId, $storedPayload['identifier'] ?? null);
        $this->assertSame(
            array_merge($context->toArray(), ['identifier' => 'author-ctx-' . $authorId]),
            $storedPayload
        );

        $rehydrated = new Author;
        $rehydrated->setRawAttributes([
            'id' => $authorId,
            'context' => $stored,
        ], true);

        $this->assertInstanceOf(AuthorContext::class, $rehydrated->context);
        $this->assertSame('author-ctx-' . $authorId, $rehydrated->context->getIdentifier());
        $this->assertSame($storedPayload, $rehydrated->context->toArray());
    }

    public function test_it_accepts_array_payload_and_casts_to_context(): void
    {
        $authorId = '01JZKQ8N4W2M5X7Y9ABCDEFGH';
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
        $author->setAttribute('id', $authorId);
        $author->context = $payload;

        $this->assertInstanceOf(AuthorContext::class, $author->context);
        $this->assertSame('author-ctx-' . $authorId, $author->context->getIdentifier());

        $contextArray = $author->context->toArray();
        $this->assertSame(
            array_merge($payload['cognitive_context'], ['weight' => null]),
            $contextArray['cognitive_context'] ?? null
        );
    }

    public function test_it_sets_author_context_identifier_from_author_id_when_setting_context_instance(): void
    {
        $authorId = '01JZKQ8N4W2M5X7Y9ABCDEFGH';
        $context = (new AuthorContext)
            ->setCognitiveContext(
                (new CognitiveContext)->setWorldview('Long-term consistency beats short-term novelty.')
            );

        $author = new Author;
        $author->setAttribute('id', $authorId);
        $author->context = $context;

        $this->assertSame('author-ctx-' . $authorId, $author->context->getIdentifier());
        $this->assertSame(
            'author-ctx-' . $authorId,
            json_decode($author->getAttributes()['context'], true)['identifier'] ?? null
        );
    }

    public function test_it_overrides_stored_context_identifier_on_hydrate(): void
    {
        $authorId = '01JZKQ8N4W2M5X7Y9ABCDEFGH';
        $stored = json_encode([
            'identifier' => 'legacy-random-identifier',
            'cognitive_context' => [
                'description' => 'Defines the core belief system and logical processing of the author. This prevents the content from falling into the "neutrality trap".',
                'value' => [
                    'worldview' => [
                        'description' => 'A dense, 2-3 sentence statement defining the author\'s fundamental lens on reality.',
                        'value' => 'Consistency compounds.',
                    ],
                ],
            ],
        ]);

        $author = new Author;
        $author->setRawAttributes([
            'id' => $authorId,
            'context' => $stored,
        ], true);

        $this->assertInstanceOf(AuthorContext::class, $author->context);
        $this->assertSame('author-ctx-' . $authorId, $author->context->getIdentifier());
    }

    public function test_it_sets_author_context_identifier_for_empty_context(): void
    {
        $authorId = '01JZKQ8N4W2M5X7Y9ABCDEFGH';

        $author = new Author;
        $author->setAttribute('id', $authorId);
        $author->context = null;

        $this->assertInstanceOf(AuthorContext::class, $author->context);
        $this->assertSame('author-ctx-' . $authorId, $author->context->getIdentifier());
    }

    public function test_it_sets_author_context_identifier_when_hydrating_from_json_string(): void
    {
        $authorId = '01JZKQ8N4W2M5X7Y9ABCDEFGH';
        $stored = json_encode([
            'identifier' => 'should-be-replaced',
            'cognitive_context' => [
                'description' => 'Defines the core belief system and logical processing of the author. This prevents the content from falling into the "neutrality trap".',
                'value' => [
                    'worldview' => [
                        'description' => 'A dense, 2-3 sentence statement defining the author\'s fundamental lens on reality.',
                        'value' => 'Ship small, learn fast.',
                    ],
                ],
            ],
        ]);

        $author = new Author;
        $author->setRawAttributes([
            'id' => $authorId,
            'context' => $stored,
        ], true);

        $this->assertSame('author-ctx-' . $authorId, $author->context->getIdentifier());
    }
}
