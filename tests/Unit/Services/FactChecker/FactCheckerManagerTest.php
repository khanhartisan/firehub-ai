<?php

namespace Tests\Unit\Services\FactChecker;

use App\Contracts\CommonData\Point;
use App\Facades\FactChecker;
use App\Services\FactChecker\Drivers\BasicFactCheckerDriver;
use App\Services\FactChecker\FactCheckerManager;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FactCheckerManagerTest extends TestCase
{
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

    public function test_it_returns_basic_driver_when_requested_explicitly(): void
    {
        $driver = $this->manager()->driver('basic');

        $this->assertInstanceOf(BasicFactCheckerDriver::class, $driver);
    }

    public function test_basic_driver_returns_verification_payload(): void
    {
        Config::set('factchecker.drivers.basic.min_confidence', 0.6);

        $verification = $this->manager()->driver('basic')->verifyPoint(
            (new Point)
                ->setHeadline('A test claim')
                ->setDescription('Claim details')
                ->setEvidences(['Source 1', 'Source 2'])
        );

        $this->assertTrue($verification->getIsValid());
        $this->assertNotNull($verification->getConfidence());
        $this->assertNotNull($verification->getReasoning());
    }

    public function test_facade_root_is_fact_checker_manager(): void
    {
        $manager = FactChecker::getFacadeRoot();

        $this->assertInstanceOf(FactCheckerManager::class, $manager);
    }
}
