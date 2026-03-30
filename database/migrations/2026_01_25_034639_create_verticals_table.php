<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            Schema::connection($this->connection)->ensureVectorExtensionExists();
        }

        Schema::create('verticals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('parent_id')->nullable();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->softDeletes();
            $table->cascades();
            $table->index(['cascade_status', 'deleted_at']);

            // Intentionally no FK constraint for scale/performance; keep an index for tree queries.
            $table->index('parent_id');

            $table->vector('vector', config('vectordb.drivers.pgvector.default_dimension'))->nullable()->index();
            $table->boolean('is_embeddable')->default(false);
            $table->boolean('is_embedded')->default(false);
            $table->index(['is_embeddable', 'is_embedded', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verticals');
    }
};
