<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VECTOR_DIMENSION = 1536;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection($this->connection)->getDriverName() === 'pgsql') {
            Schema::connection($this->connection)->ensureVectorExtensionExists();
        }

        Schema::create('tags', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->unique();
            $table->timestamps();

            $table->softDeletes();
            $table->cascades();
            $table->index(['cascade_status', 'deleted_at']);

            $table->vector('vector', self::VECTOR_DIMENSION)->nullable()->index();
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
        Schema::dropIfExists('tags');
    }
};
