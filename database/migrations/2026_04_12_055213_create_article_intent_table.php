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
        Schema::create('article_intent', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('article_id');
            $table->ulid('intent_id');
            $table->decimal('relevance', 3)->nullable();
            $table->timestamps();

            $table->unique(['intent_id', 'article_id']);
            $table->index(['article_id', 'relevance']);
            $table->index(['intent_id', 'relevance']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_intent');
    }
};
