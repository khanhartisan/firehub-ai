<?php

namespace Tests\Unit\Services\Synthesizer\Critic;

use App\Contracts\CommonData\SemanticContext;
use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Contracts\Synthesizer\Critic\Criticism;
use App\Contracts\Synthesizer\Critic\Rectification;
use App\Services\Synthesizer\Critic\CriticManager;
use App\Services\Synthesizer\Critic\Drivers\BasicCriticDriver;
use Tests\TestCase;

class BasicCriticDriverTest extends TestCase
{
    public function test_it_flags_empty_and_thin_sections_under_clarity_purpose(): void
    {
        $emptySection = (new Element)
            ->setType(ElementType::DIV)
            ->setIdentifier('empt');

        $thinSection = (new Element)
            ->setType(ElementType::DIV)
            ->setIdentifier('thin')
            ->addChild(
                (new Element)
                    ->setType(ElementType::P)
                    ->addChild('Too short.')
            );

        $healthySection = (new Element)
            ->setType(ElementType::DIV)
            ->setIdentifier('hlth')
            ->addChild(
                (new Element)
                    ->setType(ElementType::P)
                    ->addChild(str_repeat('This section has enough supporting detail. ', 8))
            );

        $article = new Article;
        $article->addChild($emptySection);
        $article->addChild($thinSection);
        $article->addChild($healthySection);

        $driver = $this->basicDriver('clarity');
        $criticisms = $driver->criticizeArticle($article);

        $references = array_map(
            static fn (Criticism $criticism): ?string => $criticism->getReference(),
            $criticisms
        );

        $this->assertContains('empt', $references);
        $this->assertContains('thin', $references);
        $this->assertNotContains('hlth', $references);
        $this->assertContains('clarity', array_map(
            static fn (Criticism $criticism): ?string => $criticism->getPurpose(),
            $criticisms
        ));
    }

    public function test_it_skips_sections_already_rectified(): void
    {
        $section = (new Element)
            ->setType(ElementType::DIV)
            ->setIdentifier('rect');

        $article = new Article;
        $article->addChild($section);

        $driver = $this->basicDriver('clarity');
        $criticisms = $driver->criticizeArticle(
            $article,
            lastRectifications: [
                (new Rectification)->setReference('rect'),
            ],
        );

        $this->assertSame([], $criticisms);
    }

    public function test_it_flags_missing_author_context_keywords_under_voice_purpose(): void
    {
        $article = (new Article)->setIdentifier('root');
        $article->addChild(
            (new Element)
                ->setType(ElementType::P)
                ->addChild(str_repeat('Generic body copy without the expected keyword present. ', 6))
        );

        $authorContext = new SemanticContext;
        $authorContext->set(
            'brand_voice',
            'Required brand voice keyword.',
            'moonlighting narrative tone for founders'
        );

        $driver = $this->basicDriver('voice');
        $criticisms = $driver->criticizeArticle($article, $authorContext);

        $articleCriticism = array_values(array_filter(
            $criticisms,
            static fn (Criticism $criticism): bool => $criticism->getReference() === 'root'
        ));

        $this->assertCount(1, $articleCriticism);
        $this->assertSame('voice', $articleCriticism[0]->getPurpose());
        $this->assertStringContainsString('moonlighting', $articleCriticism[0]->getRemarks()[0]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function basicDriver(string $purpose, array $config = []): BasicCriticDriver
    {
        return new BasicCriticDriver($this->app->make(CriticManager::class), $purpose, $config);
    }
}
