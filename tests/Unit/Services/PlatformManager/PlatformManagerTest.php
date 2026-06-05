<?php

namespace Tests\Unit\Services\PlatformManager;

use App\Contracts\PlatformManager\FlyCms\Config;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoFlyCmsDriver;
use Tests\TestCase;

class PlatformManagerTest extends TestCase
{
    public function test_clone_creates_independent_copy_with_cloned_config(): void
    {
        $original = (new PseudoFlyCmsDriver)->setConfig(new Config([
            'base_url' => 'https://flycms.test',
            'api_key' => 'secret-key',
        ]));

        $cloned = $original->clone();

        $this->assertNotSame($original, $cloned);
        $this->assertInstanceOf(PseudoFlyCmsDriver::class, $cloned);
        $this->assertNotSame($original->getConfig(), $cloned->getConfig());
        $this->assertSame($original->getConfig()->toArray(), $cloned->getConfig()->toArray());

        $cloned->getConfig()->setConfig([
            'base_url' => 'https://other.test',
            'api_key' => 'new-key',
        ]);

        $this->assertSame('https://flycms.test', $original->getConfig()->getBaseUrl());
        $this->assertSame('secret-key', $original->getConfig()->getApiKey());
        $this->assertSame('https://other.test', $cloned->getConfig()->getBaseUrl());
        $this->assertSame('new-key', $cloned->getConfig()->getApiKey());
    }

    public function test_clone_without_config_returns_manager_with_null_config(): void
    {
        $original = new PseudoFlyCmsDriver;

        $cloned = $original->clone();

        $this->assertNotSame($original, $cloned);
        $this->assertInstanceOf(PseudoFlyCmsDriver::class, $cloned);
        $this->assertNull($cloned->getConfig());
    }
}
