<?php

namespace Tests\Unit\Contracts\PlatformManager;

use App\Contracts\PlatformManager\FlyCms\Config;
use Tests\TestCase;

class ConfigTest extends TestCase
{
    public function test_clone_creates_independent_copy_with_same_payload(): void
    {
        $original = new Config([
            'base_url' => 'https://flycms.test',
            'api_key' => 'secret-key',
        ]);

        $cloned = $original->clone();

        $this->assertNotSame($original, $cloned);
        $this->assertInstanceOf(Config::class, $cloned);
        $this->assertSame($original->toArray(), $cloned->toArray());
        $this->assertSame('https://flycms.test', $cloned->getBaseUrl());
        $this->assertSame('secret-key', $cloned->getApiKey());

        $cloned->setConfig([
            'base_url' => 'https://other.test',
            'api_key' => 'new-key',
        ]);

        $this->assertSame('https://flycms.test', $original->getBaseUrl());
        $this->assertSame('secret-key', $original->getApiKey());
        $this->assertSame('https://other.test', $cloned->getBaseUrl());
        $this->assertSame('new-key', $cloned->getApiKey());
    }
}
