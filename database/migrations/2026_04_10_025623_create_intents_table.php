<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('intents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('types')->nullable()->index();
            $table->unsignedInteger('keywords_count')->default(0);
            $table->unsignedInteger('pages_count')->default(0);
            $table->unsignedInteger('articles_count')->default(0);
            $table->timestamps();

            $table->fullText(['title', 'description']);

            $table->vector('vector', config('vectordb.drivers.pgvector.default_dimension'))->nullable()->index();
            $table->boolean('is_embeddable')->default(false);
            $table->boolean('is_embedded')->default(false);
            $table->index(['is_embeddable', 'is_embedded', 'updated_at']);

            $table->softDeletes();
            $table->cascades();
            $table->index(['cascade_status', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intents');
    }
};
