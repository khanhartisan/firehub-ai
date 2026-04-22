<?php

namespace Tests\Unit\Models;

use App\Contracts\Model\Article\Context;
use App\Models\Article;
use Tests\TestCase;

class ArticleContextCastTest extends TestCase
{
    public function test_it_dehydrates_and_hydrates_context_perfectly(): void
    {
        $context = (new Context)
            ->setToneOfVoice('Practical and clear')
            ->setGuidelines(['Keep it concise', 'Use examples'])
            ->setMeta([
                'raw_text' => 'Draft a practical tutorial',
                'priority' => 2,
            ]);

        $article = new Article;
        $article->context = $context;

        $this->assertIsString($article->getAttributes()['context']);
        $this->assertSame($context->toArray(), json_decode($article->getAttributes()['context'], true));

        $rehydrated = new Article;
        $rehydrated->setRawAttributes([
            'context' => $article->getAttributes()['context'],
        ], true);

        $this->assertInstanceOf(Context::class, $rehydrated->context);
        $this->assertSame($context->toArray(), $rehydrated->context->toArray());
    }

    public function test_it_accepts_string_payload_as_raw_text_meta(): void
    {
        $article = new Article;
        $article->context = 'plain-text context';

        $this->assertInstanceOf(Context::class, $article->context);
        $this->assertSame('plain-text context', $article->context->getMetaValue()['raw_text'] ?? null);
    }
}

