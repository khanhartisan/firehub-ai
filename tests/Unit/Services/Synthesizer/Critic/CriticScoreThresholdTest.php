<?php

namespace Tests\Unit\Services\Synthesizer\Critic;

use App\Contracts\DOM\Article;
use App\Contracts\DOM\Element;
use App\Contracts\DOM\ElementType;
use App\Services\Synthesizer\Critic\CriticManager;
use App\Services\Synthesizer\Critic\Drivers\BasicCriticDriver;
use Tests\TestCase;

class CriticScoreThresholdTest extends TestCase
{
    public function test_basic_driver_omits_voice_criticism_when_thresholds_are_raised_in_config(): void
    {
        $article = (new Article)->setIdentifier('root');
        $article->addChild(
            (new Element)
                ->setType(ElementType::P)
                ->addChild(str_repeat('Generic body copy without the expected keyword present. ', 6))
        );

        $authorContext = new \App\Contracts\CommonData\SemanticContext;
        $authorContext->set(
            'brand_voice',
            'Required brand voice keyword.',
            'moonlighting narrative tone for founders'
        );

        $driver = new BasicCriticDriver($this->app->make(CriticManager::class), 'voice', [
            'min_confidence' => 0.85,
            'min_importance' => 0.8,
        ]);

        $this->assertSame([], $driver->criticizeArticle($article, $authorContext));
    }

    public function test_basic_driver_keeps_criticism_when_scores_meet_default_thresholds(): void
    {
        $article = new Article;
        $article->addChild(
            (new Element)
                ->setType(ElementType::DIV)
                ->setIdentifier('sect')
                ->addChild(
                    (new Element)
                        ->setType(ElementType::P)
                        ->addChild('Too short.')
                )
        );

        $driver = new BasicCriticDriver($this->app->make(CriticManager::class), 'clarity');
        $criticisms = $driver->criticizeArticle($article);

        $this->assertNotEmpty($criticisms);
        $this->assertGreaterThanOrEqual(0.8, $criticisms[0]->getConfidence());
        $this->assertGreaterThanOrEqual(0.7, $criticisms[0]->getImportance());
    }
}
