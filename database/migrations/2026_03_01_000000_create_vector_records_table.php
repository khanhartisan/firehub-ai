<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Single table for all vector indexes; "partition" column stores the collection name.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('vector_records', function (Blueprint $table) {
            $table->string('partition', 255);
            $table->string('id', 255);
            $table->vector('embedding', 1536);
            $table->jsonb('metadata')->default('{}');
            $table->primary(['partition', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vector_records');
    }
};
