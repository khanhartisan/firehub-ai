<?php

namespace App\Services\VectorDB;

use Illuminate\Support\Manager;

class VectorDBManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('vectordb.default', 'pgvector');
    }

    /**
     * Create the pgvector driver instance.
     */
    protected function createPgvectorDriver(): Drivers\PgVectorDriver
    {
        $config = $this->config->get('vectordb.drivers.pgvector', []);

        return new Drivers\PgVectorDriver($config);
    }
}
