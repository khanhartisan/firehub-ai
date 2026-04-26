<?php

namespace Tests\Unit\Models;

use App\Contracts\DOM\Article as DOMArticle;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Models\Article;
use Tests\TestCase;

class ArticleArticleCastTest extends TestCase
{
    public function test_it_dehydrates_and_hydrates_article_dom_perfectly(): void
    {
        $domArticle = (new DOMArticle)
            ->setChildren([
                (new Element)->setType(ElementType::H2)->addChild('Introduction'),
                (new Element)->setType(ElementType::P)->addChild('This is a short intro paragraph.'),
            ]);

        $article = new Article;
        $article->article = $domArticle;

        $this->assertIsString($article->getAttributes()['article']);
        $this->assertSame($domArticle->toArray(), json_decode($article->getAttributes()['article'], true));

        $rehydrated = new Article;
        $rehydrated->setRawAttributes([
            'article' => $article->getAttributes()['article'],
        ], true);

        $this->assertInstanceOf(DOMArticle::class, $rehydrated->article);
        $this->assertSame($domArticle->toArray(), $rehydrated->article->toArray());
    }

    public function test_it_accepts_array_payload_and_casts_to_dom_article(): void
    {
        $payload = [
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Array payload paragraph'],
                ],
            ],
        ];

        $article = new Article;
        $article->article = $payload;

        $this->assertInstanceOf(DOMArticle::class, $article->article);
        $this->assertSame($payload, $article->article->toArray());
    }
}
