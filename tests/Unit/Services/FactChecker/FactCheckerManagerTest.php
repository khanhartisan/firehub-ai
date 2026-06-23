<?php

namespace Tests\Unit\Services\FactChecker;

use App\Contracts\CommonData\Conflict;
use App\Contracts\CommonData\Fact;
use App\Contracts\CommonData\Point;
use App\Contracts\CommonData\SemanticContext;
use App\Contracts\OpenAI\OpenAIClient;
use App\Facades\FactChecker;
use App\Services\FactChecker\Drivers\BasicFactCheckerDriver;
use App\Services\FactChecker\Drivers\OpenAICompatibleFactCheckerDriver;
use App\Services\FactChecker\Drivers\OpenAIFactCheckerDriver;
use App\Services\FactChecker\FactCheckerManager;
use App\Services\OpenAI\OpenAIManager;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class FactCheckerManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function manager(): FactCheckerManager
    {
        return app('factchecker.manager');
    }

    public function test_it_returns_default_basic_driver(): void
    {
        Config::set('factchecker.default', 'basic');

        $driver = $this->manager()->driver();

        $this->assertInstanceOf(BasicFactCheckerDriver::class, $driver);
    }

    public function test_it_returns_openai_driver_when_requested_explicitly(): void
    {
        $this->app->instance(OpenAIClient::class, Mockery::mock(OpenAIClient::class));

        $driver = $this->manager()->driver('openai');

        $this->assertInstanceOf(OpenAIFactCheckerDriver::class, $driver);
    }

    public function test_it_returns_basic_driver_when_requested_explicitly(): void
    {
        $driver = $this->manager()->driver('basic');

        $this->assertInstanceOf(BasicFactCheckerDriver::class, $driver);
    }

    public function test_it_returns_openai_compatible_driver_when_requested_explicitly(): void
    {
        $mockOpenAIManager = Mockery::mock(OpenAIManager::class);
        $mockOpenAIClient = Mockery::mock(OpenAIClient::class);
        $mockOpenAIManager->shouldReceive('driver')->with('openai_compatible')->andReturn($mockOpenAIClient);
        $this->app->instance(OpenAIManager::class, $mockOpenAIManager);

        $driver = $this->manager()->driver('openai_compatible');

        $this->assertInstanceOf(OpenAICompatibleFactCheckerDriver::class, $driver);
    }

    public function test_basic_driver_returns_verification_payload(): void
    {
        Config::set('factchecker.drivers.basic.min_confidence', 0.6);

        $verification = $this->manager()->driver('basic')->verify(
            (new Point)
                ->setHeadline('A test claim')
                ->setDescription('Claim details')
                ->setEvidences(['Source 1', 'Source 2'])
        );

        $this->assertTrue($verification->getIsValid());
        $this->assertNotNull($verification->getConfidence());
        $this->assertNotNull($verification->getReasoning());
    }

    public function test_basic_driver_resolves_conflict_by_returning_facts(): void
    {
        $conflict = (new Conflict)
            ->setFacts([
                new Fact('Claim A'),
                new Fact('Claim B'),
            ])
            ->setRationale('Conflicting sources');

        $resolvedFacts = $this->manager()->driver('basic')->resolveConflict($conflict, new SemanticContext());

        $this->assertCount(2, $resolvedFacts);
        $this->assertSame('Claim A', $resolvedFacts[0]->getFact());
        $this->assertSame('Claim B', $resolvedFacts[1]->getFact());
    }

    public function test_facade_root_is_fact_checker_manager(): void
    {
        $manager = FactChecker::getFacadeRoot();

        $this->assertInstanceOf(FactCheckerManager::class, $manager);
    }
}
