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
            $table->string('keyword');
            $table->char('hash', 40)->unique(); // sha1 hash from the keyword

            $table->unsignedInteger('global_volume')->nullable();
            $table->jsonb('volume_by_country')->nullable()->index();

            $table->unsignedInteger('difficulty')->nullable();

            $table->unsignedInteger('intents_count')->default(0);
            $table->unsignedInteger('pages_count')->default(0);

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
