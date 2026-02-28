<?php

namespace Tests\Unit\Services\VectorDB;

use App\Services\VectorDB\Drivers\PgVectorDriver;
use App\Services\VectorDB\VectorDBManager;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class VectorDBManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function manager(): VectorDBManager
    {
        return app('vectordb.manager');
    }

    public function test_it_returns_default_driver(): void
    {
        Config::set('vectordb.default', 'pgvector');

        $manager = $this->manager();

        $driver = $manager->driver();

        $this->assertInstanceOf(PgVectorDriver::class, $driver);
    }

    public function test_it_returns_specified_driver(): void
    {
        $manager = $this->manager();

        $driver = $manager->driver('pgvector');

        $this->assertInstanceOf(PgVectorDriver::class, $driver);
    }

    public function test_it_uses_config_for_driver_creation(): void
    {
        Config::set('vectordb.drivers.pgvector', [
            'connection' => 'pgsql',
            'table' => 'vector_records',
            'default_dimension' => 768,
        ]);

        $manager = $this->manager();

        $driver = $manager->driver('pgvector');

        $this->assertInstanceOf(PgVectorDriver::class, $driver);
    }

    public function test_get_default_driver_returns_pgvector(): void
    {
        Config::set('vectordb.default', 'pgvector');

        $manager = $this->manager();

        $this->assertEquals('pgvector', $manager->getDefaultDriver());
    }

    public function test_get_default_driver_uses_config(): void
    {
        Config::set('vectordb.default', 'pgvector');

        $manager = $this->manager();

        $this->assertEquals('pgvector', $manager->getDefaultDriver());
    }

    public function test_facade_returns_vector_db_manager_instance(): void
    {
        $manager = $this->manager();

        $this->assertInstanceOf(VectorDBManager::class, $manager);
    }
}
