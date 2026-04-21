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
        Schema::create('keywords', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('language')->nullable();
            $table->string('country')->nullable();
            $table->string('keyword');
            $table->char('hash', 40)->unique(); // sha1 hash from the keyword

            $table->unsignedInteger('volume')->nullable();

            $table->unsignedInteger('difficulty')->nullable();

            $table->unsignedInteger('intents_count')->default(0);
            $table->unsignedInteger('pages_count')->default(0);

            $table->unsignedTinyInteger('status')->default(\App\Enums\KeywordStatus::PENDING->value);
            $table->dateTime('researched_at')->nullable();
            $table->index(['status', 'researched_at']);
            $table->index(['status', 'updated_at']);

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->longText('error_logs')->nullable();

            $table->jsonb('search_engine_data')->nullable();

            $table->timestamps();
            $table->dateTime('intent_resolved_at')->nullable();
            $table->index(['intent_resolved_at', 'updated_at'], 'keywords_intent_resolved_at_index');

            $table->fullText('keyword');

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
        Schema::dropIfExists('keywords');
    }
};
