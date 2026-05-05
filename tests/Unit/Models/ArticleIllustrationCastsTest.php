<?php

namespace Tests\Unit\Models;

use App\Contracts\DOM\Article as DOMArticle;
use App\Contracts\Filesystem\File;
use App\Contracts\Model\Article\IllustrationData;
use App\Contracts\Synthesizer\Author\IllustrationAnchor;
use App\Contracts\Synthesizer\Illustration\IllustrationContext;
use App\Contracts\Synthesizer\Illustration\IllustrationResult;
use App\Models\Article;
use Tests\TestCase;

class ArticleIllustrationCastsTest extends TestCase
{
    public function test_it_dehydrates_and_hydrates_illustration_data_perfectly(): void
    {
        $result = (new IllustrationResult)
            ->setIllustrationContext(
                (new IllustrationContext)
                    ->setSubject('Intro visual')
                    ->setGoal('Explain opening concept')
                    ->setStyle('Flat editorial')
            )
            ->addFile((new File)->setPath('illustrations/generated/test-a.png'));

        $anchor = new IllustrationAnchor(
            $result->getIdentifier(),
            'el-1',
            true,
        );

        $illustration = (new IllustrationData)
            ->setIllustrationResults([$result])
            ->setIllustrationAnchors([$anchor]);

        $article = new Article;
        $article->illustration = $illustration;

        $this->assertIsString($article->getAttributes()['illustration']);
        $this->assertSame($illustration->toArray(), json_decode($article->getAttributes()['illustration'], true));

        $rehydrated = new Article;
        $rehydrated->setRawAttributes([
            'illustration' => $article->getAttributes()['illustration'],
        ], true);

        $this->assertInstanceOf(IllustrationData::class, $rehydrated->illustration);
        $this->assertSame($illustration->toArray(), $rehydrated->illustration->toArray());
    }

    public function test_illustrated_article_cast_composes_images_without_mutating_base_article(): void
    {
        $baseArticle = DOMArticle::fromArray([
            'identifier' => 'article-root',
            'type' => 'article',
            'props' => [],
            'children' => [
                [
                    'identifier' => 'section-1',
                    'type' => 'p',
                    'props' => [],
                    'children' => ['Hello world'],
                ],
            ],
        ]);

        $result = (new IllustrationResult)
            ->setIllustrationContext((new IllustrationContext)->setSubject('Section visual'))
            ->addFile((new File)->setPath('illustrations/generated/test-b.png'));

        $anchor = new IllustrationAnchor(
            $result->getIdentifier(),
            'section-1',
            true,
        );

        $illustration = (new IllustrationData)
            ->setIllustrationResults([$result])
            ->setIllustrationAnchors([$anchor]);

        $article = new Article;
        $article->article = $baseArticle;
        $article->illustration = $illustration;

        $this->assertStringNotContainsString('<img', $article->article->toHtml());

        $illustrated = $article->illustrated_article;
        $this->assertInstanceOf(DOMArticle::class, $illustrated);
        $this->assertStringContainsString('<img', $illustrated->toHtml());
        $this->assertStringContainsString('illustrations/generated/test-b.png', $illustrated->toHtml());
        $this->assertStringContainsString('Section visual', $illustrated->toHtml());

        // Base article remains unchanged; only illustrated_article is composed.
        $this->assertStringNotContainsString('<img', $article->article->toHtml());
    }
}
