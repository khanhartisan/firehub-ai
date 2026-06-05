<?php

namespace Tests;

use App\Services\PlatformManager\FlyCms\Drivers\PseudoFlyCmsDriver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Keep test runs deterministic regardless of environment overrides.
        Config::set('synthesizer.default', 'basic');
        Config::set('flycms.default', 'pseudo');
        PseudoFlyCmsDriver::reset();
    }
}
