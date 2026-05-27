<?php

namespace Tests\Unit\Services\Synthesizer\Critic;

use App\Services\Synthesizer\Critic\ArticleCritics\VoiceArticleCritic;
use App\Services\Synthesizer\Critic\CriticManager;
use App\Services\Synthesizer\Critic\Drivers\BasicCriticDriver;
use App\Services\Synthesizer\Critic\Drivers\OpenAICriticDriver;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class CriticManagerTest extends TestCase
{
    public function test_it_resolves_basic_critic_for_a_purpose(): void
    {
        $manager = $this->app->make(CriticManager::class);

        $critic = $manager->makeCritic('clarity', 'basic');

        $this->assertInstanceOf(BasicCriticDriver::class, $critic);
        $this->assertSame('clarity', $critic->getPurpose());
    }

    public function test_it_resolves_openai_critic_for_a_purpose(): void
    {
        $manager = $this->app->make(CriticManager::class);

        $critic = $manager->makeCritic('voice', 'openai');

        $this->assertInstanceOf(OpenAICriticDriver::class, $critic);
        $this->assertSame('voice', $critic->getPurpose());
    }

    public function test_it_builds_one_critic_per_registered_purpose(): void
    {
        $manager = $this->app->make(CriticManager::class);
        $critics = $manager->getCritics('basic');

        $this->assertCount(4, $critics);
        $this->assertSame(['voice', 'structure', 'clarity', 'fingerprint'], array_map(
            static fn ($critic) => $critic->getPurpose(),
            $critics
        ));
    }

    public function test_driver_method_is_not_supported(): void
    {
        $manager = $this->app->make(CriticManager::class);

        $this->expectException(LogicException::class);

        $manager->driver('basic');
    }

    public function test_it_resolves_known_article_critics(): void
    {
        $manager = $this->app->make(CriticManager::class);

        $this->assertInstanceOf(VoiceArticleCritic::class, $manager->makeArticleCritic('voice'));
    }

    public function test_it_rejects_unknown_article_critics(): void
    {
        $manager = $this->app->make(CriticManager::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown article critic purpose "factual"');

        $manager->makeArticleCritic('factual');
    }

    public function test_it_resolves_all_registered_purposes(): void
    {
        $manager = $this->app->make(CriticManager::class);

        foreach ($manager->purposes() as $purpose) {
            $this->assertSame($purpose, $manager->makeArticleCritic($purpose)->getPurpose());
        }
    }
}
